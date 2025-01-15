<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\adm\KategoriSurat;
use App\Models\adm\RegisterSurat;
use App\Models\layanan\JenisLayanan;
use App\Models\layanan\Pelayanan;
use App\Models\layanan\DataIdentitasPemohon;
use App\Models\Layanan\DokumenPengajuan;
use App\Models\Layanan\JenisPelayanan;
use App\Models\MasterOption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LayananController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $user = User::findOrFail($userId);


        
        if ($user->role === 'user') {
            $layanan = Pelayanan::with(['jenisPelayanan', 'dataIdentitas', 'dokumenPengajuan', 'statusLayanan', 'surat'])
                              ->where('user_id', $userId)
                              ->latest()
                              ->get();
        } else {
            $layanan = Pelayanan::with(['jenisPelayanan', 'dataIdentitas', 'dokumenPengajuan', 'statusLayanan', 'surat'])
                              ->where('status_layanan', '<>', '5')
                              ->latest()
                              ->get();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'List Layanan',
            'data' => $layanan
        ]);
    }

    public function show($id)
    {
        $layanan = Pelayanan::with([
            'jenisPelayanan',
            'jenisPelayanan.identitasPemohon',
            'jenisPelayanan.identitasPemohon.dataIdentitas' => function($query) use ($id) {
                $query->where('pelayanan_id', $id);
            },
            'jenisPelayanan.syaratDokumen',
            'jenisPelayanan.syaratDokumen.dokumenPengajuan' => function($query) use ($id) {
                $query->where('pelayanan_id', $id);
            },
            'statusLayanan',
            'surat'
        ])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'message' => 'Detail Layanan',
            'data' => $layanan
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_pelayanan_id' => 'required|exists:duk_jenis_pelayanan,id',
            'catatan' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'id' => 'nullable|exists:duk_pelayanan,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $statusLayanan = MasterOption::where(['value' => 'Draft', 'type' => 'status_layanan'])->first();
        $request->merge(['status_layanan' => $statusLayanan->id]);

        $layanan = Pelayanan::updateOrCreate(
            [
                'id' => $request->id,
                'user_id' => $request->user_id,
            ],
            [
                'jenis_pelayanan_id' => $request->jenis_pelayanan_id,
                'catatan' => $request->catatan,
                'status_layanan' => $request->status_layanan,
            ]
        );
        

        $layanan->load('jenisPelayanan', 'dataIdentitas', 'dokumenPengajuan', 'statusLayanan');

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil disimpan',
            'data' => $layanan
        ], 201);
    }

    public function uploadIdentitas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identitas_pemohon_id.*' => 'required|exists:duk_identitas_pemohon,id',
            'nilai.*' => 'nullable|string',
            'pelayanan_id' => 'required|exists:duk_pelayanan,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        foreach ($request->identitas_pemohon_id as $key => $value) {
            $dataIdentitas = DataIdentitasPemohon::updateOrCreate(
                ['identitas_pemohon_id' => $value, 'pelayanan_id' => $request->pelayanan_id],
                ['nilai' => $request->nilai[$key]]
            );
        }

        $identitas = DataIdentitasPemohon::where('pelayanan_id', $request->pelayanan_id)->get();


        return response()->json([
            'success' => true,
            'message' => 'Data identitas berhasil diperbarui',
            'data' => $identitas
        ], 201);
    }

    public function uploadDokumen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'syarat_dokumen_id' => 'required|array',
            'syarat_dokumen_id.*' => 'required|exists:duk_syarat_dokumen,id',
            'path' => 'nullable|array',
            'path.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'pelayanan_id' => 'required|exists:duk_pelayanan,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->syarat_dokumen_id as $key => $dokumenId) {
                // Jika ada file yang diupload
                if ($request->hasFile("path.{$key}")) {
                    $file = $request->file("path.{$key}");
                    $filename = time() . '_' . $dokumenId . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('dokumen_pengajuan', $filename, 'public');

                    // Update atau create dokumen
                    DokumenPengajuan::updateOrCreate(
                        [
                            'syarat_dokumen_id' => $dokumenId,
                            'pelayanan_id' => $request->pelayanan_id,
                        ],
                        [
                            'path_dokumen' => $path,
                            'uploaded_at' => now(),
                        ]
                    );
                } else {
                    // Jika tidak ada file, tetap create record tanpa path
                    DokumenPengajuan::updateOrCreate(
                        [
                            'syarat_dokumen_id' => $dokumenId,
                            'pelayanan_id' => $request->pelayanan_id,
                        ],
                        [
                            'uploaded_at' => now(),
                        ]
                    );
                }
            }

            // Ambil semua dokumen untuk pelayanan ini
            $dokumen = DokumenPengajuan::with('syaratDokumen')
                ->where('pelayanan_id', $request->pelayanan_id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil disimpan',
                'data' => $dokumen
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan dokumen',
                'error' => $e->getMessage()
            ], 500);
        }
    }   

    public function finalisasi(Request $request, $id)
    {
        // $validator = Validator::make($request->all(), [
        //     'id' => 'required|exists:duk_pelayanan,id',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 422);
        // }

        $layanan = Pelayanan::findOrFail($id);

        $statusLayanan = MasterOption::where(['value' => 'Belum Diproses', 'type' => 'status_layanan'])->first();

        $layanan->update(['status_layanan' => $statusLayanan->id]);
        
        $layanan->load(['jenisPelayanan', 'dataIdentitas', 'dokumenPengajuan', 'statusLayanan']);

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil di finalisasi',
            'data' => $layanan
        ], 200);
    }

    public function approve(Request $request, $id)
    {
        // $validator = Validator::make($request->all(), [
        //     'id' => 'required|exists:duk_pelayanan,id',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 422);
        // }

        $layanan = Pelayanan::findOrFail($id);

        $statusLayanan = MasterOption::where(['value' => 'Sedang Diproses', 'type' => 'status_layanan'])->first();

        $upd = $layanan->update(['status_layanan' => $statusLayanan->id]);
        
        if($upd){
            $no_surat = generateNoSurat();
            $kategori_surat = KategoriSurat::where('nama', 'Layanan')->first()->id;
            $jenis_surat = $layanan->jenisPelayanan->nama_pelayanan;
            $perihal = $layanan->jenisPelayanan->nama_pelayanan;
            $tanggal_surat = now();
            $status_surat = MasterOption::where(['value' => 'Proses', 'type' => 'status_surat'])->first()->id;
            $signed_by = User::where('role', 'Kepala')->first()->id;

            $ins_reg_surat = RegisterSurat::create([
                'nomor_surat' => $no_surat,
                'kategori_surat_id' => $kategori_surat,
                'jenis_surat' => $jenis_surat,
                'perihal' => $perihal,
                'tanggal_surat' => $tanggal_surat,
                'status' => $status_surat,
                'signer_id' => $signed_by,
            ]);

            if($ins_reg_surat){
                $layanan->update(['surat_id' => $ins_reg_surat->id]);
            }
        }

        $layanan->load(['jenisPelayanan', 'dataIdentitas', 'dokumenPengajuan', 'statusLayanan']);

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil di approve',
            'data' => $layanan
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'persyaratan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $layanan = Pelayanan::findOrFail($id);
        $layanan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil diupdate',
            'data' => $layanan
        ]);
    }

    public function destroy($id)
    {
        $layanan = Pelayanan::findOrFail($id);
        $layanan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil dihapus'
        ]);
    }

    public function getJenisLayanan()
    {
        $jenisLayanan = JenisPelayanan::get();
        
        return response()->json([
            'success' => true,
            'message' => 'List Jenis Layanan',
            'data' => $jenisLayanan
        ]);
    }
} 