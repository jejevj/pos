<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌐 Seeding site settings...');

        $settings = [
            ['key' => 'site_name', 'value' => 'POS System', 'type' => 'string', 'group' => 'general', 'label' => 'Nama Situs', 'description' => 'Nama aplikasi yang ditampilkan di browser dan header'],
            ['key' => 'site_tagline', 'value' => 'Sistem POS Modern', 'type' => 'string', 'group' => 'general', 'label' => 'Tagline', 'description' => 'Slogan atau deskripsi singkat situs'],
            ['key' => 'site_description', 'value' => '', 'type' => 'text', 'group' => 'general', 'label' => 'Deskripsi Situs', 'description' => 'Deskripsi lengkap untuk SEO dan meta tag'],
            ['key' => 'site_logo', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'Logo', 'description' => 'Logo utama situs (rekomendasi: PNG transparan, min 200x60px)'],
            ['key' => 'site_logo_dark', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'Logo Dark Mode', 'description' => 'Logo untuk dark mode / latar gelap'],
            ['key' => 'site_favicon', 'value' => '', 'type' => 'image', 'group' => 'branding', 'label' => 'Favicon', 'description' => 'Icon tab browser (rekomendasi: ICO atau PNG 32x32px)'],
            ['key' => 'primary_color', 'value' => '#06b6d4', 'type' => 'color', 'group' => 'branding', 'label' => 'Warna Utama', 'description' => 'Warna tema utama aplikasi'],
            ['key' => 'contact_email', 'value' => '', 'type' => 'string', 'group' => 'contact', 'label' => 'Email Kontak', 'description' => 'Email yang ditampilkan di halaman kontak'],
            ['key' => 'contact_phone', 'value' => '', 'type' => 'string', 'group' => 'contact', 'label' => 'Telepon', 'description' => 'Nomor telepon kontak'],
            ['key' => 'contact_address', 'value' => '', 'type' => 'text', 'group' => 'contact', 'label' => 'Alamat', 'description' => 'Alamat fisik'],
            ['key' => 'social_instagram', 'value' => '', 'type' => 'string', 'group' => 'social', 'label' => 'Instagram', 'description' => 'URL profil Instagram'],
            ['key' => 'social_facebook', 'value' => '', 'type' => 'string', 'group' => 'social', 'label' => 'Facebook', 'description' => 'URL halaman Facebook'],
            ['key' => 'social_twitter', 'value' => '', 'type' => 'string', 'group' => 'social', 'label' => 'Twitter / X', 'description' => 'URL profil Twitter/X'],
            ['key' => 'footer_text', 'value' => '© 2026 POS System. All rights reserved.', 'type' => 'string', 'group' => 'general', 'label' => 'Teks Footer', 'description' => 'Teks copyright di bagian bawah halaman'],
        ];

        foreach ($settings as $s) {
            SiteSetting::firstOrCreate(
                ['key' => $s['key']],
                $s
            );
        }

        $this->command->info('✅ Site settings seeded: ' . count($settings));
    }
}
