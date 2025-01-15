<!-- Footer -->
<footer class="footer" id="kontak">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5>Tentang {{ $settings->app_name ?? 'Desa Banyupelle' }}</h5>
                <p>{{ $profil[0]->deskripsi ?? 'Desa Banyupelle adalah desa yang terletak di Kecamatan Palengaan, Kabupaten Pamekasan, Jawa Timur.' }}</p>
            </div>
            <div class="col-lg-4 mb-4">
                <h5>Kontak</h5>
                <p>
                    <i class="fas fa-map-marker-alt me-2"></i> {{ $settings->address ?? 'Alamat belum diatur' }}<br>
                    <i class="fas fa-phone me-2"></i> {{ $settings->phone ?? 'Telepon belum diatur' }}<br>
                    <i class="fas fa-envelope me-2"></i> {{ $settings->email ?? 'Email belum diatur' }}
                </p>
            </div>
            <div class="col-lg-4 mb-4">
                <h5>Ikuti Kami</h5>
                <div class="social-links">
                    <a href="{{ $settings->facebook_url ?? '#' }}" target="_blank"><i class="fab fa-facebook"></i></a>
                    <a href="{{ $settings->instagram_url ?? '#' }}" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="{{ $settings->youtube_url ?? '#' }}" target="_blank"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
        <hr class="mt-4 mb-4">
        <div class="row">
            <div class="col-lg-12 text-center">
                <p class="mb-0">&copy; 2024 {{ $settings->app_name ?? 'Desa Banyupelle' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer> 