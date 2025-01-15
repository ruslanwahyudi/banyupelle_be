<?php

namespace App\Http\Controllers\layanan;

use App\Http\Controllers\Controller;
use App\Models\layanan\Layanan;
use App\Models\layanan\JenisLayanan;
use App\Models\layanan\PersyaratanDokumen;
use App\Models\layanan\IdentitasLayanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DaftarLayananController extends Controller
{
    public function index()
    {
        if (request()->ajax()) {
            $layanan = Layanan::with(['jenis', 'identitas', 'persyaratan'])->latest()->get();
            return response()->json($layanan);
        }

        return view('layanan.daftar.index');
    }

    public function create()
    {
        $jenis = JenisLayanan::where('status', true)->get();
        $identitas = IdentitasLayanan::where('status', true)->get();
        $persyaratan = PersyaratanDokumen::where('status', true)->get();

        return view('layanan.daftar.create', compact('jenis', 'identitas', 'persyaratan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenis_layanan_id' => 'required|exists:jenis_layanan,id',
            'identitas_layanan_id' => 'required|exists:identitas_layanan,id',
            'persyaratan_dokumen' => 'required|array',
            'persyaratan_dokumen.*' => 'exists:persyaratan_dokumen,id',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'file_pendukung' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
            'status' => 'required|boolean'
        ]);

        try {
            if ($request->hasFile('file_pendukung')) {
                $file = $request->file('file_pendukung');
                $nama_file = 'layanan-' . Str::slug($request->nama) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('public/layanan', $nama_file);
                $path = str_replace('public/', 'storage/', $path);
            }

            $layanan = Layanan::create([
                'jenis_layanan_id' => $request->jenis_layanan_id,
                'identitas_layanan_id' => $request->identitas_layanan_id,
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'file_pendukung' => $path ?? null,
                'status' => $request->status,
                'user_id' => auth()->id()
            ]);

            $layanan->persyaratan()->attach($request->persyaratan_dokumen);

            return redirect()->route('layanan.daftar')
                ->with('success', 'Layanan berhasil ditambahkan!');
        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::delete(str_replace('storage/', 'public/', $path));
            }
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show(Layanan $layanan)
    {
        $layanan->load(['jenis', 'identitas', 'persyaratan']);
        return response()->json($layanan);
    }

    public function edit(Layanan $layanan)
    {
        $jenis = JenisLayanan::where('status', true)->get();
        $identitas = IdentitasLayanan::where('status', true)->get();
        $persyaratan = PersyaratanDokumen::where('status', true)->get();
        $selected_persyaratan = $layanan->persyaratan->pluck('id')->toArray();

        return view('layanan.daftar.edit', compact('layanan', 'jenis', 'identitas', 'persyaratan', 'selected_persyaratan'));
    }

    public function update(Request $request, Layanan $layanan)
    {
        $request->validate([
            'jenis_layanan_id' => 'required|exists:jenis_layanan,id',
            'identitas_layanan_id' => 'required|exists:identitas_layanan,id',
            'persyaratan_dokumen' => 'required|array',
            'persyaratan_dokumen.*' => 'exists:persyaratan_dokumen,id',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'file_pendukung' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
            'status' => 'required|boolean'
        ]);

        try {
            $old_file = $layanan->file_pendukung;

            if ($request->hasFile('file_pendukung')) {
                $file = $request->file('file_pendukung');
                $nama_file = 'layanan-' . Str::slug($request->nama) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('public/layanan', $nama_file);
                $path = str_replace('public/', 'storage/', $path);

                if ($old_file) {
                    Storage::delete(str_replace('storage/', 'public/', $old_file));
                }
            }

            $layanan->update([
                'jenis_layanan_id' => $request->jenis_layanan_id,
                'identitas_layanan_id' => $request->identitas_layanan_id,
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'file_pendukung' => $path ?? $old_file,
                'status' => $request->status
            ]);

            $layanan->persyaratan()->sync($request->persyaratan_dokumen);

            return redirect()->route('layanan.daftar')
                ->with('success', 'Layanan berhasil diperbarui!');
        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::delete(str_replace('storage/', 'public/', $path));
            }
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(Layanan $layanan)
    {
        try {
            if ($layanan->file_pendukung) {
                Storage::delete(str_replace('storage/', 'public/', $layanan->file_pendukung));
            }

            $layanan->persyaratan()->detach();
            $layanan->delete();

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Layanan berhasil dihapus!'
                ]);
            }

            return redirect()->route('layanan.daftar')
                ->with('success', 'Layanan berhasil dihapus!');
        } catch (\Exception $e) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function search($search)
    {
        $layanan = Layanan::with(['jenis', 'identitas', 'persyaratan'])
            ->where('nama', 'like', "%{$search}%")
            ->orWhere('deskripsi', 'like', "%{$search}%")
            ->orWhereHas('jenis', function($query) use ($search) {
                $query->where('nama', 'like', "%{$search}%");
            })
            ->orWhereHas('identitas', function($query) use ($search) {
                $query->where('nama', 'like', "%{$search}%");
            })
            ->get();

        return response()->json($layanan);
    }
} 