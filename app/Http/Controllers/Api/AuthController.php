<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Roles_type;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Access\AuthorizationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required_without:email|string|unique:users,phone',
            'email' => 'required_without:phone|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
            'fcm_token' => 'nullable|string' // untuk push notification
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'role_id' => Roles_type::where('name', 'User')->first()->id,
            'password' => Hash::make($request->password),
            'fcm_token' => $request->fcm_token
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        if($user){
            UserProfile::create([
                'user_id' => $user->id,
                'nik' => '',
                'nama_lengkap' => $request->name,
                'tanggal_lahir' => '2000-01-01',
                'tempat_lahir' => 'Pamekasan',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Pamekasan',
                'no_hp' => '',
                'foto' => 'default.png',   
                'status_pernikahan' => '',
                'pekerjaan' => '',
                'kewarganegaraan' => '',
                'agama' => ''
            ]);
        }   

        $profile = UserProfile::where('user_id', $user->id)->first();
        if(!$profile){
            $profile = new UserProfile([
                'user_id' => $user->id,
                'nik' => '',
                'nama' => '',
                'tempat_lahir' => '',
                'tanggal_lahir' => '',
                'jenis_kelamin' => '',
                'alamat' => '',
                'no_hp' => '',
                'foto' => '',
                'status_pernikahan' => '',
                'pekerjaan' => '',
                'kewarganegaraan' => '',
                'agama' => ''
            ]);
        }
        $user->profile = $profile;

        // Kirim email verifikasi
        $user->notify(new \App\Notifications\CustomVerifyEmail);

        return response()->json([
            'success' => true,
            'message' => 'Register successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }

    public function resendEmailVerificationNotification(Request $request)
    {
        $request->user()->notify(new \App\Notifications\CustomVerifyEmail);
        return response()->json([
            'success' => true,
            'message' => 'Email verification berhasil dikirim, silahkan cek email anda'
        ]);
    }

    public function verifyEmail(Request $request)
    {
        try {
            $user = User::findOrFail($request->route('id'));
            
            if ($user->email_verified_at) {
                // return response()->json([
                //     'success' => false,
                //     'message' => 'Email already verified'
                // ]);
                // tampilkan return pesan dengan format html , bukan json
                return '<h1>Email sudah terverifikasi</h1>';
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Email has been verified successfully'
            // ]);
            return '<h1>Email berhasil terverifikasi</h1>';

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error verifying email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|string',
            'email' => 'required_without:phone|email',
            'password' => 'required',
            'fcm_token' => 'nullable|string' // untuk push notification
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Check if login using phone or email
        $credentials = $request->has('email') 
            ? ['email' => $request->email, 'password' => $request->password]
            : ['phone' => $request->phone, 'password' => $request->password];

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = Auth::user();
        
        // Update FCM token jika ada
        if ($request->fcm_token) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        

        $profile = UserProfile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new UserProfile([
                'user_id' => $user->id,
                'nik' => '',
                'nama_lengkap' => $user->name,
                'tempat_lahir' => 'Pamekasan',
                'tanggal_lahir' => '2000-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Pamekasan',
                'no_hp' => '',  
                'foto' => 'default.png',
                'status_pernikahan' => '',
                'pekerjaan' => '',
                'kewarganegaraan' => '',
                'agama' => ''
            ]);
            $profile->save();
        }

        $user->profile = $profile;

        $user->profile->foto = $profile->getFotoUrlAttribute();
        // if($profile->foto){
        //     $user->profile->foto = $profile->getFotoUrlAttribute();
        // }else{
        //     $user->profile->foto = $profile->getFotoUrlAttribute().'/default.png';
        // }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // Clear FCM token saat logout
        $request->user()->update(['fcm_token' => null]);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    public function uploadSelfie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'selfie' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();
        $profile = UserProfile::where('user_id', $user->id)->first();

        if ($request->hasFile('selfie')) {
            // Hapus foto lama jika ada
            if ($profile->foto) {
                Storage::delete('public/selfies/' . $profile->foto);
            }

            $file = $request->file('selfie');
            $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/selfies', $filename);

            $profile->update([
                'foto' => $filename
            ]);

            // Update user profile
            $user->update(['status' => 'N']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto selfie berhasil diupload, menunggu verifikasi admin',
            'data' => [
                'user' => $user,
                'profile' => $profile
            ]
        ]);
    }

    public function verifyUser(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Y,N',
            'keterangan' => 'required_if:status,rejected|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::findOrFail($userId);

        $user->update([
            'status' => $request->status === 'Y' ? 'Y' : 'N'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status verifikasi user berhasil diupdate',
            'data' => [
                'user' => $user
            ]
        ]);
    }

    public function checkVerificationStatus(Request $request)
    {
        $user = $request->user();
        $profile = UserProfile::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Status verifikasi user',
            'data' => [
                'status' => $user->status
            ]
        ]);
    }
} 