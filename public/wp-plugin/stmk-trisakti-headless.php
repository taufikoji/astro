<?php
/**
 * Plugin Name:  STMK Trisakti – Headless CMS
 * Description:  Custom Post Types, meta fields, CORS, custom roles, deploy trigger,
 *               dan admin panel khusus untuk staff non-IT STMK Trisakti.
 * Version:      2.0.0
 * Author:       STMK Trisakti
 * Text Domain:  stmk-headless
 *
 * ═══════════════════════════════════════════════════════════════
 * CARA INSTALL
 * ═══════════════════════════════════════════════════════════════
 * 1. Salin folder ini ke: wp-content/plugins/stmk-trisakti-headless/
 * 2. Aktifkan dari menu Plugins di WordPress dashboard
 * 3. Buka Pengaturan Website STMK → isi GitHub Token, Owner, Repo
 * 4. Set WORDPRESS_API_URL di GitHub repository secrets
 *    (Settings → Secrets → Actions → New repository secret)
 *
 * CATATAN MIGRASI:
 * - Plugin ini menggantikan Sveltia CMS untuk pengelolaan berita.
 * - Migrasi 11 file .md di src/content/berita/ ke WordPress Posts (manual, sekali).
 * - Setelah verifikasi: hapus src/pages/admin/index.astro dan public/admin/config.yml
 *
 * PERLU CURL:
 * - Pastikan extension=curl aktif di php.ini XAMPP untuk fungsi deploy.
 * ═══════════════════════════════════════════════════════════════
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ────────────────────────────────────────────────────────────────────
// ACTIVATION / DEACTIVATION
// ────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, 'stmk_activate' );
function stmk_activate() {
    stmk_register_post_types();
    stmk_setup_roles();
    flush_rewrite_rules();
    // Tandai sebagai instalasi baru untuk onboarding notice
    if ( ! get_option( 'stmk_setup_complete' ) ) {
        update_option( 'stmk_setup_complete', 0 );
    }
}

register_deactivation_hook( __FILE__, 'stmk_deactivate' );
function stmk_deactivate() {
    stmk_remove_roles();
    flush_rewrite_rules();
}

// ────────────────────────────────────────────────────────────────────
// SECTION 1 — CUSTOM POST TYPES
// ────────────────────────────────────────────────────────────────────

add_action( 'init', 'stmk_register_post_types' );
function stmk_register_post_types() {

    // DOSEN
    register_post_type( 'dosen', [
        'labels'          => [
            'name'          => 'Dosen',
            'singular_name' => 'Dosen',
            'add_new_item'  => 'Tambah Dosen',
            'edit_item'     => 'Edit Dosen',
            'search_items'  => 'Cari Dosen',
        ],
        'public'          => true,
        'show_in_rest'    => true,
        'rest_base'       => 'dosen',
        'supports'        => [ 'title', 'editor', 'thumbnail' ],
        'has_archive'     => false,
        'menu_icon'       => 'dashicons-welcome-learn-more',
        'menu_position'   => 5,
        'capability_type' => [ 'dosen', 'dosens' ],
        'map_meta_cap'    => true,
    ] );

    // LOWONGAN KERJA
    register_post_type( 'lowongan', [
        'labels'          => [
            'name'          => 'Lowongan Kerja',
            'singular_name' => 'Lowongan',
            'add_new_item'  => 'Tambah Lowongan',
            'edit_item'     => 'Edit Lowongan',
        ],
        'public'          => true,
        'show_in_rest'    => true,
        'rest_base'       => 'lowongan',
        'supports'        => [ 'title', 'editor' ],
        'has_archive'     => false,
        'menu_icon'       => 'dashicons-clipboard',
        'menu_position'   => 6,
        'capability_type' => [ 'lowongan', 'lowongans' ],
        'map_meta_cap'    => true,
    ] );

    // TESTIMONI ALUMNI
    register_post_type( 'testimoni', [
        'labels'          => [
            'name'          => 'Testimoni Alumni',
            'singular_name' => 'Testimoni',
            'add_new_item'  => 'Tambah Testimoni',
            'edit_item'     => 'Edit Testimoni',
        ],
        'public'          => true,
        'show_in_rest'    => true,
        'rest_base'       => 'testimoni',
        'supports'        => [ 'title', 'editor', 'thumbnail' ],
        'has_archive'     => false,
        'menu_icon'       => 'dashicons-format-quote',
        'menu_position'   => 7,
        'capability_type' => [ 'testimoni', 'testimonis' ],
        'map_meta_cap'    => true,
    ] );

    // GALERI KARYA
    register_post_type( 'galeri', [
        'labels'          => [
            'name'          => 'Galeri Karya',
            'singular_name' => 'Karya',
            'add_new_item'  => 'Tambah Karya',
            'edit_item'     => 'Edit Karya',
        ],
        'public'          => true,
        'show_in_rest'    => true,
        'rest_base'       => 'galeri',
        'supports'        => [ 'title', 'editor', 'thumbnail' ],
        'has_archive'     => false,
        'menu_icon'       => 'dashicons-format-gallery',
        'menu_position'   => 8,
        'capability_type' => [ 'galeri', 'galeris' ],
        'map_meta_cap'    => true,
    ] );
}

// ────────────────────────────────────────────────────────────────────
// SECTION 2 — META FIELDS (exposed via REST API)
// ────────────────────────────────────────────────────────────────────

add_action( 'init', 'stmk_register_meta_fields' );
function stmk_register_meta_fields() {
    $str = [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
    ];

    foreach ( [ 'gelar', 'jabatan', 'bidang_keahlian', 'nidn', 'prodi', 'email' ] as $f )
        register_post_meta( 'dosen', $f, $str );

    foreach ( [ 'perusahaan', 'lokasi', 'tipe', 'deadline', 'kontak_email', 'prodi', 'persyaratan' ] as $f )
        register_post_meta( 'lowongan', $f, $str );

    foreach ( [ 'posisi', 'perusahaan', 'prodi', 'angkatan', 'kutipan_singkat' ] as $f )
        register_post_meta( 'testimoni', $f, $str );

    foreach ( [ 'kategori', 'prodi', 'konsentrasi', 'tahun', 'link_drive' ] as $f )
        register_post_meta( 'galeri', $f, $str );
}

// ────────────────────────────────────────────────────────────────────
// SECTION 3 — CORS
// ────────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', 'stmk_cors_allow', 15 );
function stmk_cors_allow() {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
    add_filter( 'rest_pre_serve_request', function ( $value ) {
        $allowed = [
            'http://localhost:4321', 'http://localhost:4322',
            'http://localhost:4323', 'http://localhost:4324',
            'https://taufikoji.github.io',
            'https://trisaktimultimedia.ac.id',
            'https://www.trisaktimultimedia.ac.id',
        ];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        header( in_array( $origin, $allowed, true )
            ? "Access-Control-Allow-Origin: $origin"
            : 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
        if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) { status_header( 200 ); exit; }
        return $value;
    }, 15 );
}

// ────────────────────────────────────────────────────────────────────
// SECTION 4 — INJECT META KE REST RESPONSE
// ────────────────────────────────────────────────────────────────────

foreach ( [ 'dosen', 'lowongan', 'testimoni', 'galeri' ] as $_cpt )
    add_filter( "rest_prepare_{$_cpt}", 'stmk_inject_meta_to_response', 10, 2 );

function stmk_inject_meta_to_response( $response, $post ) {
    $data  = $response->get_data();
    $clean = [];
    foreach ( get_post_meta( $post->ID ) as $key => $val ) {
        if ( substr( $key, 0, 1 ) === '_' ) continue;
        $clean[ $key ] = ( is_array( $val ) && count( $val ) === 1 ) ? $val[0] : $val;
    }
    $data['meta'] = array_merge( $data['meta'] ?? [], $clean );
    $response->set_data( $data );
    return $response;
}

// ────────────────────────────────────────────────────────────────────
// SECTION 5 — (Kategori berita pakai Categories bawaan WP)
// Buat kategori: Akademik, Kemahasiswaan, Prestasi, Kerjasama, Event
// ────────────────────────────────────────────────────────────────────

// ────────────────────────────────────────────────────────────────────
// SECTION 6 — ADMIN COLUMNS (dosen, lowongan, testimoni, galeri)
// ────────────────────────────────────────────────────────────────────

add_filter( 'manage_dosen_posts_columns', fn($c) => array_merge( $c, ['stmk_prodi'=>'Prodi','stmk_nidn'=>'NIDN'] ) );
add_action( 'manage_dosen_posts_custom_column', function( $col, $id ) {
    if ( $col === 'stmk_prodi' ) echo esc_html( get_post_meta( $id, 'prodi', true ) );
    if ( $col === 'stmk_nidn' )  echo esc_html( get_post_meta( $id, 'nidn', true ) );
}, 10, 2 );

add_filter( 'manage_lowongan_posts_columns', fn($c) => array_merge( $c, ['stmk_perusahaan'=>'Perusahaan','stmk_deadline'=>'Deadline'] ) );
add_action( 'manage_lowongan_posts_custom_column', function( $col, $id ) {
    if ( $col === 'stmk_perusahaan' ) echo esc_html( get_post_meta( $id, 'perusahaan', true ) );
    if ( $col === 'stmk_deadline' )   echo esc_html( get_post_meta( $id, 'deadline', true ) );
}, 10, 2 );

add_filter( 'manage_testimoni_posts_columns', fn($c) => array_merge( $c, ['stmk_tperusahaan'=>'Perusahaan','stmk_tprodi'=>'Prodi'] ) );
add_action( 'manage_testimoni_posts_custom_column', function( $col, $id ) {
    if ( $col === 'stmk_tperusahaan' ) echo esc_html( get_post_meta( $id, 'perusahaan', true ) );
    if ( $col === 'stmk_tprodi' )      echo esc_html( get_post_meta( $id, 'prodi', true ) );
}, 10, 2 );

add_filter( 'manage_galeri_posts_columns', fn($c) => array_merge( $c, ['stmk_gkategori'=>'Kategori','stmk_gtahun'=>'Tahun'] ) );
add_action( 'manage_galeri_posts_custom_column', function( $col, $id ) {
    if ( $col === 'stmk_gkategori' ) echo esc_html( get_post_meta( $id, 'kategori', true ) );
    if ( $col === 'stmk_gtahun' )    echo esc_html( get_post_meta( $id, 'tahun', true ) );
}, 10, 2 );

// ════════════════════════════════════════════════════════════════════
// SECTION 7 — CUSTOM USER ROLES
// ════════════════════════════════════════════════════════════════════

function stmk_setup_roles() {
    // Staff Humas/Marketing — kelola berita, galeri, testimoni
    add_role( 'stmk_humas', 'Staff Humas', [
        'read'                      => true,
        'upload_files'              => true,
        // Berita (posts)
        'edit_posts'                => true,
        'edit_published_posts'      => true,
        'publish_posts'             => true,
        'delete_posts'              => true,
        'delete_published_posts'    => true,
        // Galeri CPT
        'edit_galeris'              => true,
        'edit_published_galeris'    => true,
        'publish_galeris'           => true,
        'delete_galeris'            => true,
        'delete_published_galeris'  => true,
        // Testimoni CPT
        'edit_testimonis'           => true,
        'edit_published_testimonis' => true,
        'publish_testimonis'        => true,
        'delete_testimonis'         => true,
        'delete_published_testimonis' => true,
    ] );

    // Staff Akademik — kelola dosen
    add_role( 'stmk_akademik', 'Staff Akademik', [
        'read'                    => true,
        'upload_files'            => true,
        'edit_dosens'             => true,
        'edit_published_dosens'   => true,
        'publish_dosens'          => true,
        'delete_dosens'           => true,
        'delete_published_dosens' => true,
    ] );

    // Staff PMB — kelola lowongan
    add_role( 'stmk_pmb', 'Staff PMB', [
        'read'                        => true,
        'upload_files'                => true,
        'edit_lowongans'              => true,
        'edit_published_lowongans'    => true,
        'publish_lowongans'           => true,
        'delete_lowongans'            => true,
        'delete_published_lowongans'  => true,
    ] );

    // Pimpinan — read-only + bisa trigger rebuild
    add_role( 'stmk_pimpinan', 'Pimpinan', [
        'read'                 => true,
        'stmk_trigger_rebuild' => true,
    ] );

    // Tambah semua CPT capabilities ke Administrator
    // (diperlukan karena capability_type custom tidak otomatis ke admin)
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( 'stmk_trigger_rebuild' );
        foreach ( [ 'dosen', 'lowongan', 'testimoni', 'galeri' ] as $cpt ) {
            $p = $cpt . 's'; // plural
            $admin->add_cap( "edit_{$p}" );
            $admin->add_cap( "edit_others_{$p}" );
            $admin->add_cap( "publish_{$p}" );
            $admin->add_cap( "read_private_{$p}" );
            $admin->add_cap( "delete_{$p}" );
            $admin->add_cap( "delete_private_{$p}" );
            $admin->add_cap( "delete_published_{$p}" );
            $admin->add_cap( "delete_others_{$p}" );
            $admin->add_cap( "edit_private_{$p}" );
            $admin->add_cap( "edit_published_{$p}" );
            $admin->add_cap( "create_{$p}" );
        }
    }
}

function stmk_remove_roles() {
    foreach ( [ 'stmk_humas', 'stmk_akademik', 'stmk_pmb', 'stmk_pimpinan' ] as $role )
        remove_role( $role );
    $admin = get_role( 'administrator' );
    if ( $admin ) $admin->remove_cap( 'stmk_trigger_rebuild' );
}

// ════════════════════════════════════════════════════════════════════
// SECTION 8 — ROLE-BASED MENU RESTRICTIONS
// ════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'stmk_restrict_admin_menus', 999 );
function stmk_restrict_admin_menus() {
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    // Menu slugs yang diizinkan per role
    $allowed = [
        'stmk_humas'    => [ 'edit.php', 'upload.php', 'edit.php?post_type=galeri', 'edit.php?post_type=testimoni' ],
        'stmk_akademik' => [ 'edit.php?post_type=dosen', 'upload.php' ],
        'stmk_pmb'      => [ 'edit.php?post_type=lowongan', 'upload.php' ],
        'stmk_pimpinan' => [ 'index.php' ],
    ];

    $matched_role = null;
    foreach ( $allowed as $role => $_ ) {
        if ( in_array( $role, $roles, true ) ) { $matched_role = $role; break; }
    }
    if ( ! $matched_role ) return; // Administrator/lainnya: jangan dibatasi

    global $menu;
    $whitelist = $allowed[ $matched_role ];
    foreach ( $menu as $item ) {
        $slug = $item[2] ?? '';
        if ( $slug && ! in_array( $slug, $whitelist, true ) ) {
            remove_menu_page( $slug );
        }
    }
}

// Blokir akses URL langsung ke halaman yang tidak diizinkan
add_action( 'current_screen', 'stmk_block_direct_access' );
function stmk_block_direct_access() {
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    $blocked = [
        'stmk_humas'    => [ 'dosen', 'lowongan', 'options-general', 'plugins', 'users' ],
        'stmk_akademik' => [ 'post', 'galeri', 'testimoni', 'lowongan', 'options-general', 'plugins', 'users' ],
        'stmk_pmb'      => [ 'post', 'dosen', 'galeri', 'testimoni', 'options-general', 'plugins', 'users' ],
        'stmk_pimpinan' => [ 'post', 'dosen', 'galeri', 'testimoni', 'lowongan', 'options-general', 'plugins', 'users' ],
    ];

    $matched = null;
    foreach ( $blocked as $role => $_ ) {
        if ( in_array( $role, $roles, true ) ) { $matched = $role; break; }
    }
    if ( ! $matched ) return;

    $screen = get_current_screen();
    if ( $screen && in_array( $screen->id, $blocked[ $matched ], true ) ) {
        wp_die( __( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'stmk-headless' ), 403 );
    }
}

// ════════════════════════════════════════════════════════════════════
// SECTION 9 — CUSTOM META BOXES
// ════════════════════════════════════════════════════════════════════

add_action( 'admin_init', function() {
    foreach ( [ 'dosen', 'lowongan', 'testimoni', 'galeri' ] as $cpt )
        remove_post_type_support( $cpt, 'custom-fields' );
} );

add_action( 'add_meta_boxes', 'stmk_register_meta_boxes' );
function stmk_register_meta_boxes() {
    add_meta_box( 'stmk_dosen_meta', 'Data Dosen', 'stmk_render_dosen_meta', 'dosen', 'normal', 'high' );
    add_meta_box( 'stmk_lowongan_meta', 'Detail Lowongan', 'stmk_render_lowongan_meta', 'lowongan', 'normal', 'high' );
    add_meta_box( 'stmk_testimoni_meta', 'Data Alumni', 'stmk_render_testimoni_meta', 'testimoni', 'normal', 'high' );
    add_meta_box( 'stmk_galeri_meta', 'Detail Karya', 'stmk_render_galeri_meta', 'galeri', 'normal', 'high' );
}

// ── Helper: render satu baris field ──────────────────────────────
function stmk_field( $post_id, $key, $label, $type = 'text', $options = [], $hint = '' ) {
    $val = esc_attr( get_post_meta( $post_id, $key, true ) );
    echo '<tr><th style="width:180px;padding:8px 12px 8px 0;vertical-align:top">';
    echo '<label for="stmk_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
    echo '</th><td style="padding:6px 0">';

    if ( $type === 'select' ) {
        echo '<select id="stmk_' . esc_attr($key) . '" name="stmk_' . esc_attr($key) . '" style="min-width:200px">';
        foreach ( $options as $opt ) {
            echo '<option value="' . esc_attr($opt) . '"' . selected( $val, $opt, false ) . '>' . esc_html($opt) . '</option>';
        }
        echo '</select>';
    } elseif ( $type === 'textarea' ) {
        echo '<textarea id="stmk_' . esc_attr($key) . '" name="stmk_' . esc_attr($key) . '" rows="4" style="width:100%;max-width:500px">' . esc_textarea( get_post_meta( $post_id, $key, true ) ) . '</textarea>';
    } else {
        echo '<input type="' . esc_attr($type) . '" id="stmk_' . esc_attr($key) . '" name="stmk_' . esc_attr($key) . '" value="' . $val . '" style="width:100%;max-width:400px">';
    }

    if ( $hint ) echo '<p class="description" style="margin-top:4px">' . esc_html($hint) . '</p>';
    echo '</td></tr>';
}

// ── Dosen ────────────────────────────────────────────────────────
function stmk_render_dosen_meta( $post ) {
    wp_nonce_field( 'stmk_save_meta', 'stmk_nonce' );
    echo '<p style="color:#666;margin-bottom:8px">✏️ <em>Nama lengkap ditulis di kolom <strong>Judul</strong> di atas. Foto dosen unggah via <strong>Gambar Unggulan</strong>.</em></p>';
    echo '<table style="width:100%">';
    stmk_field( $post->ID, 'gelar', 'Gelar Akademik', 'text', [], 'Contoh: S.Sn., M.Si.' );
    stmk_field( $post->ID, 'nidn', 'NIDN', 'text', [], '10 digit Nomor Induk Dosen Nasional' );
    stmk_field( $post->ID, 'jabatan', 'Jabatan Fungsional', 'select', [ '-', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar', 'Dosen Tetap', 'Dosen Praktisi' ] );
    stmk_field( $post->ID, 'prodi', 'Program Studi', 'select', [ 'DKV', 'Teknologi Grafika', 'Umum' ] );
    stmk_field( $post->ID, 'bidang_keahlian', 'Bidang Keahlian', 'text', [], 'Contoh: Desain Grafis & Animasi' );
    stmk_field( $post->ID, 'email', 'Email Dosen', 'email' );
    echo '</table>';
}

// ── Lowongan ─────────────────────────────────────────────────────
function stmk_render_lowongan_meta( $post ) {
    wp_nonce_field( 'stmk_save_meta', 'stmk_nonce' );
    echo '<p style="color:#666;margin-bottom:8px">✏️ <em>Posisi jabatan ditulis di kolom <strong>Judul</strong>. Deskripsi lengkap perusahaan di editor bawah.</em></p>';

    // Cek apakah deadline sudah lewat
    $deadline = get_post_meta( $post->ID, 'deadline', true );
    if ( $deadline && strtotime( $deadline ) < time() ) {
        echo '<div style="background:#fef9c3;border-left:4px solid #eab308;padding:10px 14px;margin-bottom:12px">⚠️ <strong>Peringatan:</strong> Deadline lowongan ini sudah lewat (' . esc_html( $deadline ) . '). Pertimbangkan untuk memperbarui atau menghapus.</div>';
    }

    echo '<table style="width:100%">';
    stmk_field( $post->ID, 'perusahaan', 'Nama Perusahaan', 'text' );
    stmk_field( $post->ID, 'lokasi', 'Lokasi Kerja', 'text', [], 'Contoh: Jakarta Selatan' );
    stmk_field( $post->ID, 'tipe', 'Tipe Pekerjaan', 'select', [ 'Full-time', 'Part-time', 'Magang', 'Freelance', 'Contract' ] );
    stmk_field( $post->ID, 'prodi', 'Ditujukan untuk Prodi', 'select', [ 'DKV', 'Teknologi Grafika', 'Semua' ] );
    stmk_field( $post->ID, 'deadline', 'Batas Waktu Melamar', 'date' );
    stmk_field( $post->ID, 'kontak_email', 'Email Kontak HRD', 'email' );
    stmk_field( $post->ID, 'persyaratan', 'Persyaratan', 'textarea', [], 'Tulis satu persyaratan per baris. Akan ditampilkan sebagai daftar di website.' );
    echo '</table>';
}

// ── Testimoni ────────────────────────────────────────────────────
function stmk_render_testimoni_meta( $post ) {
    wp_nonce_field( 'stmk_save_meta', 'stmk_nonce' );
    echo '<p style="color:#666;margin-bottom:8px">✏️ <em>Nama alumni ditulis di kolom <strong>Judul</strong>. Foto diunggah via <strong>Gambar Unggulan</strong>.</em></p>';
    echo '<table style="width:100%">';
    stmk_field( $post->ID, 'posisi', 'Posisi / Jabatan', 'text', [], 'Contoh: Senior Art Director' );
    stmk_field( $post->ID, 'perusahaan', 'Tempat Bekerja', 'text', [], 'Contoh: Ruang Guru' );
    stmk_field( $post->ID, 'prodi', 'Program Studi Alumni', 'select', [ 'DKV', 'Teknologi Grafika' ] );
    stmk_field( $post->ID, 'angkatan', 'Angkatan (Tahun Masuk)', 'text', [], 'Contoh: 2018' );
    stmk_field( $post->ID, 'kutipan_singkat', 'Kutipan Singkat', 'textarea', [], 'Kutipan 1–3 kalimat yang ditampilkan di kartu testimoni website. Maks ±200 karakter.' );
    echo '</table>';
}

// ── Galeri ───────────────────────────────────────────────────────
function stmk_render_galeri_meta( $post ) {
    wp_nonce_field( 'stmk_save_meta', 'stmk_nonce' );
    echo '<p style="color:#666;margin-bottom:8px">✏️ <em>Judul karya ditulis di kolom <strong>Judul</strong>. Gambar diunggah via <strong>Gambar Unggulan</strong>.</em></p>';
    $years = [];
    for ( $y = (int) date('Y'); $y >= 2018; $y-- ) $years[] = (string) $y;
    echo '<table style="width:100%">';
    stmk_field( $post->ID, 'kategori', 'Kategori Karya', 'select', [ 'Karya Tugas', 'Karya Tugas Akhir', 'Event Kampus', 'Pameran' ] );
    stmk_field( $post->ID, 'prodi', 'Program Studi', 'select', [ 'DKV', 'Teknologi Grafika', 'Semua Prodi' ] );
    stmk_field( $post->ID, 'konsentrasi', 'Konsentrasi / Peminatan', 'text', [], 'Contoh: Animasi dan Games' );
    stmk_field( $post->ID, 'tahun', 'Tahun Karya', 'select', $years );
    stmk_field( $post->ID, 'link_drive', 'Link Portfolio / Google Drive', 'url', [], 'https://drive.google.com/...' );
    echo '</table>';
}

// ── Save semua meta box ─────────────────────────────────────────
add_action( 'save_post', 'stmk_save_meta_boxes' );
function stmk_save_meta_boxes( $post_id ) {
    if ( ! isset( $_POST['stmk_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['stmk_nonce'], 'stmk_save_meta' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    $post_type = get_post_type( $post_id );

    $fields = [
        'dosen'     => [ 'gelar', 'nidn', 'jabatan', 'prodi', 'bidang_keahlian' ],
        'lowongan'  => [ 'perusahaan', 'lokasi', 'tipe', 'prodi', 'deadline', 'kontak_email' ],
        'testimoni' => [ 'posisi', 'perusahaan', 'prodi', 'angkatan' ],
        'galeri'    => [ 'kategori', 'prodi', 'konsentrasi', 'tahun' ],
    ];
    $emails   = [ 'dosen' => ['email'], 'lowongan' => ['kontak_email'] ];
    $urls     = [ 'galeri' => ['link_drive'] ];
    $textareas= [ 'lowongan' => ['persyaratan'], 'testimoni' => ['kutipan_singkat'] ];

    foreach ( ( $fields[ $post_type ] ?? [] ) as $key ) {
        if ( isset( $_POST[ 'stmk_' . $key ] ) )
            update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ 'stmk_' . $key ] ) );
    }
    foreach ( ( $emails[ $post_type ] ?? [] ) as $key ) {
        if ( isset( $_POST[ 'stmk_' . $key ] ) )
            update_post_meta( $post_id, $key, sanitize_email( $_POST[ 'stmk_' . $key ] ) );
    }
    foreach ( ( $urls[ $post_type ] ?? [] ) as $key ) {
        if ( isset( $_POST[ 'stmk_' . $key ] ) )
            update_post_meta( $post_id, $key, esc_url_raw( $_POST[ 'stmk_' . $key ] ) );
    }
    foreach ( ( $textareas[ $post_type ] ?? [] ) as $key ) {
        if ( isset( $_POST[ 'stmk_' . $key ] ) )
            update_post_meta( $post_id, $key, sanitize_textarea_field( $_POST[ 'stmk_' . $key ] ) );
    }
}

// ════════════════════════════════════════════════════════════════════
// SECTION 10 — CUSTOM DASHBOARD WIDGETS
// ════════════════════════════════════════════════════════════════════

add_action( 'wp_dashboard_setup', 'stmk_setup_dashboard', 999 );
function stmk_setup_dashboard() {
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    $stmk_roles = [ 'stmk_humas', 'stmk_akademik', 'stmk_pmb', 'stmk_pimpinan' ];
    $is_stmk    = (bool) array_intersect( $stmk_roles, $roles );

    // Hapus widget default untuk semua STMK role
    if ( $is_stmk ) {
        remove_meta_box( 'dashboard_quick_press',    'dashboard', 'side' );
        remove_meta_box( 'dashboard_primary',        'dashboard', 'side' );
        remove_meta_box( 'dashboard_activity',       'dashboard', 'normal' );
        remove_meta_box( 'dashboard_right_now',      'dashboard', 'normal' );
        remove_meta_box( 'dashboard_site_health',    'dashboard', 'normal' );
        remove_meta_box( 'dashboard_php_nag',        'dashboard', 'normal' );
        remove_meta_box( 'dashboard_welcome',        'dashboard', 'normal' );
    }

    // Widget statistik + deploy (Admin dan Pimpinan)
    if ( current_user_can( 'manage_options' ) || in_array( 'stmk_pimpinan', $roles, true ) ) {
        wp_add_dashboard_widget( 'stmk_stats_widget', '📊 Statistik & Deploy Website', 'stmk_render_stats_widget' );
    }

    // Widget konten milik saya (staff operasional)
    if ( $is_stmk && ! in_array( 'stmk_pimpinan', $roles, true ) ) {
        wp_add_dashboard_widget( 'stmk_my_content', '📝 Konten Saya Terbaru', 'stmk_render_my_content_widget' );
    }
}

function stmk_render_stats_widget() {
    // Hitung konten
    $counts = [
        'Berita'   => wp_count_posts( 'post' )->publish       ?? 0,
        'Dosen'    => wp_count_posts( 'dosen' )->publish      ?? 0,
        'Lowongan' => wp_count_posts( 'lowongan' )->publish   ?? 0,
        'Galeri'   => wp_count_posts( 'galeri' )->publish     ?? 0,
        'Testimoni'=> wp_count_posts( 'testimoni' )->publish  ?? 0,
    ];

    echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px">';
    foreach ( $counts as $label => $count ) {
        echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;padding:12px;text-align:center;border-radius:6px">';
        echo '<div style="font-size:22px;font-weight:900;color:#1B3A7A">' . esc_html( $count ) . '</div>';
        echo '<div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em">' . esc_html( $label ) . '</div>';
        echo '</div>';
    }
    echo '</div>';

    // Status deploy terakhir
    $last_time   = (int) get_option( 'stmk_last_deploy_time', 0 );
    $last_status = get_option( 'stmk_last_deploy_status', 'Belum ada deploy' );
    $last_by     = get_userdata( (int) get_option( 'stmk_last_deploy_trigger_by', 0 ) );
    $last_by_name = $last_by ? $last_by->display_name : 'Sistem';

    $status_color = str_contains( $last_status, 'error' ) ? '#ef4444' : ( $last_status === 'triggered' ? '#22c55e' : '#64748b' );
    $status_label = $last_status === 'triggered' ? '✅ Berhasil dipicu' : ( str_contains( $last_status, 'error' ) ? '❌ ' . $last_status : $last_status );

    echo '<div style="background:#f1f5f9;border-radius:6px;padding:12px;margin-bottom:14px">';
    echo '<div style="font-size:11px;color:#64748b;margin-bottom:4px">STATUS DEPLOY TERAKHIR</div>';
    echo '<div style="font-size:13px;font-weight:600;color:' . $status_color . '">' . esc_html( $status_label ) . '</div>';
    if ( $last_time ) {
        echo '<div style="font-size:11px;color:#94a3b8;margin-top:4px">';
        echo human_time_diff( $last_time, time() ) . ' yang lalu &bull; oleh ' . esc_html( $last_by_name );
        echo '</div>';
    }
    echo '<div id="stmk-run-status" style="font-size:11px;color:#94a3b8;margin-top:2px"></div>';
    echo '</div>';

    // Tombol trigger rebuild
    if ( current_user_can( 'stmk_trigger_rebuild' ) ) {
        $settings = get_option( 'stmk_settings', [] );
        $configured = ! empty( $settings['github_token'] ) && ! empty( $settings['github_owner'] ) && ! empty( $settings['github_repo'] );

        if ( $configured ) {
            echo '<button id="stmk-trigger-rebuild-btn" class="button button-primary" style="width:100%;padding:8px">';
            echo '🚀 Publish Website Sekarang</button>';
            echo '<div id="stmk-deploy-status" style="margin-top:8px;font-size:12px;color:#64748b"></div>';
        } else {
            echo '<div style="background:#fef9c3;border:1px solid #fbbf24;padding:10px;border-radius:4px;font-size:12px">';
            echo '⚠️ GitHub belum dikonfigurasi. <a href="' . admin_url( 'options-general.php?page=stmk-settings' ) . '">Buka Pengaturan Website STMK</a>';
            echo '</div>';
        }
    }
}

function stmk_render_my_content_widget() {
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;
    $cpt_map = [
        'stmk_humas'    => 'post',
        'stmk_akademik' => 'dosen',
        'stmk_pmb'      => 'lowongan',
    ];
    $cpt = null;
    foreach ( $cpt_map as $role => $type ) {
        if ( in_array( $role, $roles, true ) ) { $cpt = $type; break; }
    }
    if ( ! $cpt ) return;

    $q = new WP_Query( [ 'post_type' => $cpt, 'posts_per_page' => 5, 'author' => get_current_user_id(), 'post_status' => [ 'publish', 'draft' ] ] );
    if ( ! $q->have_posts() ) {
        echo '<p style="color:#64748b">Belum ada konten. <a href="' . admin_url( 'post-new.php' . ( $cpt !== 'post' ? '?post_type=' . $cpt : '' ) ) . '">Tambah sekarang →</a></p>';
        return;
    }
    echo '<ul style="margin:0;padding:0;list-style:none">';
    while ( $q->have_posts() ) {
        $q->the_post();
        $status_badge = get_post_status() === 'publish'
            ? '<span style="background:#dcfce7;color:#166534;font-size:10px;padding:1px 6px;border-radius:99px">Publish</span>'
            : '<span style="background:#fef3c7;color:#92400e;font-size:10px;padding:1px 6px;border-radius:99px">Draft</span>';
        echo '<li style="padding:7px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">';
        echo '<a href="' . get_edit_post_link() . '" style="font-size:13px;text-decoration:none;color:#1e293b">' . esc_html( get_the_title() ) . '</a>';
        echo $status_badge . '</li>';
    }
    wp_reset_postdata();
    echo '</ul>';
}

// ════════════════════════════════════════════════════════════════════
// SECTION 11 — SETTINGS PAGE
// ════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'stmk_add_settings_menu' );
function stmk_add_settings_menu() {
    add_options_page(
        'Pengaturan Website STMK',
        'Website STMK',
        'manage_options',
        'stmk-settings',
        'stmk_render_settings_page'
    );
}

add_action( 'admin_init', 'stmk_register_settings' );
function stmk_register_settings() {
    register_setting( 'stmk_settings_group', 'stmk_settings', 'stmk_sanitize_settings' );
}

function stmk_sanitize_settings( $input ) {
    $clean = [];
    $clean['github_token']             = sanitize_text_field( $input['github_token']             ?? '' );
    $clean['github_owner']             = sanitize_text_field( $input['github_owner']             ?? '' );
    $clean['github_repo']              = sanitize_text_field( $input['github_repo']              ?? '' );
    $clean['github_branch']            = sanitize_text_field( $input['github_branch']            ?? 'main' );
    $clean['github_workflow']          = sanitize_text_field( $input['github_workflow']          ?? 'deploy.yml' );
    $clean['astro_site_url']           = esc_url_raw( $input['astro_site_url']                  ?? '' );
    $clean['auto_deploy_on_publish']   = ! empty( $input['auto_deploy_on_publish'] ) ? 1 : 0;
    $clean['deploy_cooldown_minutes']  = max( 1, (int) ( $input['deploy_cooldown_minutes'] ?? 5 ) );
    return $clean;
}

function stmk_render_settings_page() {
    $s = get_option( 'stmk_settings', [] );
    $token_set = ! empty( $s['github_token'] );
    ?>
    <div class="wrap">
    <h1>⚙️ Pengaturan Website STMK Trisakti</h1>

    <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0">
        <strong>GitHub Personal Access Token</strong> memerlukan scope <code>repo</code> (atau minimal <code>public_repo</code> + <code>actions:write</code>).
        Token disimpan di database WordPress — pastikan hanya staf berwenang yang punya akses ke server ini.
    </div>

    <form method="post" action="options.php">
    <?php settings_fields( 'stmk_settings_group' ); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th>GitHub Token</th>
            <td>
                <input type="password" name="stmk_settings[github_token]" value="<?php echo esc_attr( $s['github_token'] ?? '' ); ?>" class="regular-text">
                <?php if ( $token_set ) echo '<span style="color:#22c55e;margin-left:8px">✅ Token tersimpan</span>'; ?>
                <p class="description">Buat token di <a href="https://github.com/settings/tokens/new" target="_blank">github.com/settings/tokens</a> dengan scope <code>repo</code>.</p>
            </td>
        </tr>
        <tr>
            <th>GitHub Username / Org</th>
            <td><input type="text" name="stmk_settings[github_owner]" value="<?php echo esc_attr( $s['github_owner'] ?? 'taufikoji' ); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th>Nama Repository</th>
            <td><input type="text" name="stmk_settings[github_repo]" value="<?php echo esc_attr( $s['github_repo'] ?? 'astro' ); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th>Branch Deploy</th>
            <td><input type="text" name="stmk_settings[github_branch]" value="<?php echo esc_attr( $s['github_branch'] ?? 'main' ); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th>Nama File Workflow</th>
            <td>
                <input type="text" name="stmk_settings[github_workflow]" value="<?php echo esc_attr( $s['github_workflow'] ?? 'deploy.yml' ); ?>" class="regular-text">
                <p class="description">Nama file di folder <code>.github/workflows/</code>. Default: <code>deploy.yml</code></p>
            </td>
        </tr>
        <tr>
            <th>URL Website Astro</th>
            <td>
                <input type="url" name="stmk_settings[astro_site_url]" value="<?php echo esc_attr( $s['astro_site_url'] ?? 'https://taufikoji.github.io/astro' ); ?>" class="regular-text">
                <p class="description">Digunakan untuk tombol "Preview Website".</p>
            </td>
        </tr>
        <tr>
            <th>Auto-Deploy saat Publish</th>
            <td>
                <label>
                    <input type="checkbox" name="stmk_settings[auto_deploy_on_publish]" value="1" <?php checked( $s['auto_deploy_on_publish'] ?? 1, 1 ); ?>>
                    Otomatis trigger rebuild website setiap kali konten dipublikasikan
                </label>
            </td>
        </tr>
        <tr>
            <th>Jeda Minimal Antar Deploy</th>
            <td>
                <input type="number" name="stmk_settings[deploy_cooldown_minutes]" value="<?php echo (int) ( $s['deploy_cooldown_minutes'] ?? 5 ); ?>" min="1" max="60" style="width:80px"> menit
                <p class="description">Mencegah terlalu banyak rebuild jika banyak konten dipublish sekaligus.</p>
            </td>
        </tr>
    </table>
    <?php submit_button( 'Simpan Pengaturan' ); ?>
    </form>

    <hr>
    <h2>🔌 Test Koneksi GitHub</h2>
    <button id="stmk-test-github" class="button">Test Koneksi</button>
    <span id="stmk-test-result" style="margin-left:12px"></span>

    <?php if ( ! empty( $s['astro_site_url'] ) ) : ?>
    <hr>
    <h2>🌐 Preview Website</h2>
    <a href="<?php echo esc_url( $s['astro_site_url'] ); ?>" target="_blank" class="button button-secondary">Buka Website →</a>
    <p class="description">Buka website Astro di tab baru untuk melihat hasil deploy terakhir.</p>
    <?php endif; ?>
    </div>
    <?php
}

// ════════════════════════════════════════════════════════════════════
// SECTION 12 — GITHUB ACTIONS DEPLOY TRIGGER
// ════════════════════════════════════════════════════════════════════

/**
 * Kirim request ke GitHub API untuk trigger workflow_dispatch.
 * @param  array  $inputs  Optional inputs untuk workflow (informational)
 * @param  bool   $blocking  true untuk manual trigger (perlu feedback), false untuk auto (fire-and-forget)
 * @return true|WP_Error
 */
function stmk_dispatch_github_workflow( $inputs = [], $blocking = true ) {
    $s = get_option( 'stmk_settings', [] );
    $token    = $s['github_token']            ?? '';
    $owner    = $s['github_owner']            ?? '';
    $repo     = $s['github_repo']             ?? '';
    $branch   = $s['github_branch']           ?? 'main';
    $workflow = $s['github_workflow']         ?? 'deploy.yml';
    $cooldown = (int) ( $s['deploy_cooldown_minutes'] ?? 5 ) * 60;

    if ( ! $token || ! $owner || ! $repo ) {
        return new WP_Error( 'stmk_missing_config', 'GitHub belum dikonfigurasi. Buka Pengaturan Website STMK.' );
    }

    // Cek cooldown
    $last = (int) get_option( 'stmk_last_deploy_time', 0 );
    if ( time() - $last < $cooldown ) {
        $sisa = ceil( ( $cooldown - ( time() - $last ) ) / 60 );
        return new WP_Error( 'stmk_cooldown', "Deploy berikutnya bisa dipicu dalam {$sisa} menit lagi." );
    }

    $url  = "https://api.github.com/repos/{$owner}/{$repo}/actions/workflows/{$workflow}/dispatches";
    $body = wp_json_encode( [
        'ref'    => $branch,
        'inputs' => empty( $inputs ) ? (object) [] : $inputs,  // harus object {} bukan array []
    ] );

    $response = wp_remote_post( $url, [
        'headers'  => [
            'Authorization'        => "Bearer {$token}",
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Content-Type'         => 'application/json',
            'User-Agent'           => 'STMK-Trisakti-WP-Plugin/2.0',
        ],
        'body'     => $body,
        'timeout'  => $blocking ? 15 : 5,
        'blocking' => $blocking,
    ] );

    if ( is_wp_error( $response ) ) {
        update_option( 'stmk_last_deploy_status', 'error: ' . $response->get_error_message() );
        return $response;
    }

    // GitHub mengembalikan 204 No Content saat sukses — BUKAN 200
    $code = wp_remote_retrieve_response_code( $response );
    if ( $blocking && $code !== 204 ) {
        $msg = wp_strip_all_tags( wp_remote_retrieve_body( $response ) );
        update_option( 'stmk_last_deploy_status', "error: HTTP {$code}" );
        return new WP_Error( 'stmk_github_error', "GitHub API error {$code}: {$msg}" );
    }

    update_option( 'stmk_last_deploy_time',        time() );
    update_option( 'stmk_last_deploy_status',       'triggered' );
    update_option( 'stmk_last_deploy_trigger_by',   get_current_user_id() );
    return true;
}

// ── AJAX: trigger manual dari dashboard ─────────────────────────
add_action( 'wp_ajax_stmk_trigger_rebuild', 'stmk_ajax_trigger_rebuild' );
function stmk_ajax_trigger_rebuild() {
    check_ajax_referer( 'stmk_rebuild_nonce', 'nonce' );
    if ( ! current_user_can( 'stmk_trigger_rebuild' ) && ! current_user_can( 'manage_options' ) )
        wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );

    $result = stmk_dispatch_github_workflow( [ 'triggered_by' => 'manual' ], true );
    if ( is_wp_error( $result ) )
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );

    wp_send_json_success( [ 'message' => '🚀 Deploy berhasil dipicu! Website akan diperbarui dalam 2–5 menit.' ] );
}

// ── AJAX: cek status deploy ──────────────────────────────────────
add_action( 'wp_ajax_stmk_deploy_status', 'stmk_ajax_deploy_status' );
function stmk_ajax_deploy_status() {
    check_ajax_referer( 'stmk_rebuild_nonce', 'nonce' );
    $last_time = (int) get_option( 'stmk_last_deploy_time', 0 );
    $last_by   = get_userdata( (int) get_option( 'stmk_last_deploy_trigger_by', 0 ) );
    wp_send_json_success( [
        'last_time'   => $last_time,
        'last_status' => get_option( 'stmk_last_deploy_status', 'Belum ada deploy' ),
        'last_by'     => $last_by ? $last_by->display_name : 'Sistem',
        'human_time'  => $last_time ? human_time_diff( $last_time, time() ) . ' yang lalu' : '-',
    ] );
}

// ── AJAX: test koneksi GitHub ────────────────────────────────────
add_action( 'wp_ajax_stmk_test_github', 'stmk_ajax_test_github' );
function stmk_ajax_test_github() {
    check_ajax_referer( 'stmk_rebuild_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) )
        wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );

    $s     = get_option( 'stmk_settings', [] );
    $token = $s['github_token'] ?? '';
    $owner = $s['github_owner'] ?? '';
    $repo  = $s['github_repo']  ?? '';

    if ( ! $token || ! $owner || ! $repo )
        wp_send_json_error( [ 'message' => 'Isi token, owner, dan repo terlebih dahulu.' ] );

    $resp = wp_remote_get( "https://api.github.com/repos/{$owner}/{$repo}", [
        'headers' => [
            'Authorization'        => "Bearer {$token}",
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent'           => 'STMK-Trisakti-WP-Plugin/2.0',
        ],
        'timeout' => 10,
    ] );

    if ( is_wp_error( $resp ) )
        wp_send_json_error( [ 'message' => 'Gagal menghubungi GitHub: ' . $resp->get_error_message() ] );

    $code = wp_remote_retrieve_response_code( $resp );
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );

    if ( $code === 200 ) {
        $visibility = $data['private'] ? 'Private' : 'Public';
        wp_send_json_success( [ 'message' => "✅ Terhubung! Repo: {$owner}/{$repo} ({$visibility}). Full name: " . ( $data['full_name'] ?? '-' ) ] );
    } elseif ( $code === 404 ) {
        wp_send_json_error( [ 'message' => '❌ Repo tidak ditemukan. Periksa owner/repo dan pastikan token punya akses.' ] );
    } elseif ( $code === 401 ) {
        wp_send_json_error( [ 'message' => '❌ Token tidak valid atau kedaluwarsa.' ] );
    } else {
        wp_send_json_error( [ 'message' => "❌ Error {$code}: " . wp_strip_all_tags( $data['message'] ?? '' ) ] );
    }
}

// ── REST endpoint: status deploy (untuk polling advanced) ────────
add_action( 'rest_api_init', function() {
    register_rest_route( 'stmk/v1', '/deploy-status', [
        'methods'             => 'GET',
        'callback'            => 'stmk_rest_deploy_status',
        'permission_callback' => function() { return is_user_logged_in(); },
    ] );
} );
function stmk_rest_deploy_status() {
    $last_time = (int) get_option( 'stmk_last_deploy_time', 0 );
    $last_by   = get_userdata( (int) get_option( 'stmk_last_deploy_trigger_by', 0 ) );
    return rest_ensure_response( [
        'last_time'   => $last_time,
        'last_status' => get_option( 'stmk_last_deploy_status', 'Belum ada deploy' ),
        'last_by'     => $last_by ? $last_by->display_name : 'Sistem',
        'human_time'  => $last_time ? human_time_diff( $last_time, time() ) . ' yang lalu' : '-',
    ] );
}

// ════════════════════════════════════════════════════════════════════
// SECTION 13 — AUTO-DEPLOY ON PUBLISH
// ════════════════════════════════════════════════════════════════════

add_action( 'transition_post_status', 'stmk_auto_deploy_on_publish', 10, 3 );
function stmk_auto_deploy_on_publish( $new_status, $old_status, $post ) {
    // Hanya saat transisi ke 'publish' (bukan update existing published)
    if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

    $deploy_cpts = [ 'post', 'dosen', 'lowongan', 'testimoni', 'galeri' ];
    if ( ! in_array( $post->post_type, $deploy_cpts, true ) ) return;

    $s = get_option( 'stmk_settings', [] );
    if ( empty( $s['auto_deploy_on_publish'] ) ) return;

    // Fire-and-forget (blocking=false agar tidak blokir halaman)
    $result = stmk_dispatch_github_workflow(
        [ 'triggered_by' => 'publish_' . $post->post_type ],
        false
    );

    if ( is_wp_error( $result ) ) {
        set_transient( 'stmk_deploy_notice', [
            'type'    => 'warning',
            'message' => '⚠️ Konten berhasil dipublikasikan, tetapi deploy otomatis gagal: ' . $result->get_error_message() . ' Gunakan tombol "Publish Website" di dashboard.',
        ], 60 );
    } else {
        set_transient( 'stmk_deploy_notice', [
            'type'    => 'success',
            'message' => '✅ Konten dipublikasikan dan deploy website otomatis telah dipicu. Website akan diperbarui dalam 2–5 menit.',
        ], 60 );
    }
}

// Tampilkan transient notice setelah publish
add_action( 'admin_notices', 'stmk_show_deploy_notice' );
function stmk_show_deploy_notice() {
    $notice = get_transient( 'stmk_deploy_notice' );
    if ( ! $notice ) return;
    delete_transient( 'stmk_deploy_notice' );
    $type = esc_attr( $notice['type'] );
    echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
}

// ════════════════════════════════════════════════════════════════════
// SECTION 14 — ADMIN UI POLISH
// ════════════════════════════════════════════════════════════════════

// ── Enqueue CSS + JS untuk dashboard ────────────────────────────
add_action( 'admin_enqueue_scripts', 'stmk_enqueue_admin_assets' );
function stmk_enqueue_admin_assets( $hook ) {
    // CSS global untuk semua halaman admin
    wp_add_inline_style( 'wp-admin', stmk_get_admin_css() );

    $is_dashboard = ( $hook === 'index.php' );
    $is_settings  = ( $hook === 'settings_page_stmk-settings' );

    if ( ! $is_dashboard && ! $is_settings ) return;

    // jQuery + nonce tersedia di dashboard DAN settings page
    wp_enqueue_script( 'jquery' );
    wp_localize_script( 'jquery', 'stmkRebuild', [
        'nonce'   => wp_create_nonce( 'stmk_rebuild_nonce' ),
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
    ] );

    if ( $is_dashboard ) {
        wp_add_inline_script( 'jquery', stmk_get_dashboard_js() );
    }

    if ( $is_settings ) {
        wp_add_inline_script( 'jquery', stmk_get_settings_js() );
    }
}

function stmk_get_admin_css() {
    return '
        /* STMK Admin Branding */
        #adminmenu .wp-menu-name { font-weight: 600; }
        #wpcontent { background: #f8fafc; }
        #wpbody-content .wrap h1 { font-size: 1.5rem; font-weight: 800; }
        .stmk-badge { display:inline-block; background:#1B3A7A; color:white; font-size:10px;
                      padding:2px 8px; border-radius:99px; font-weight:700; letter-spacing:.04em; }
    ';
}

function stmk_get_dashboard_js() {
    return "
    jQuery(function($) {
        var polling = false;

        $('#stmk-trigger-rebuild-btn').on('click', function(e) {
            e.preventDefault();
            var \$btn = $(this);
            \$btn.prop('disabled', true).text('⏳ Memproses...');
            \$('#stmk-deploy-status').text('').css('color', '#64748b');

            $.post(stmkRebuild.ajaxurl, {
                action: 'stmk_trigger_rebuild',
                nonce:  stmkRebuild.nonce
            })
            .done(function(res) {
                if (res.success) {
                    \$btn.text('✅ Deploy Dipicu!').css({'background':'#22c55e','border-color':'#16a34a'});
                    \$('#stmk-deploy-status').text(res.data.message).css('color', '#16a34a');
                    stmkPoll(0);
                } else {
                    \$btn.prop('disabled', false).text('🚀 Publish Website Sekarang');
                    \$('#stmk-deploy-status').text('❌ ' + res.data.message).css('color', '#dc2626');
                }
            })
            .fail(function() {
                \$btn.prop('disabled', false).text('🚀 Publish Website Sekarang');
                \$('#stmk-deploy-status').text('❌ Gagal terhubung ke server.').css('color', '#dc2626');
            });
        });

        function stmkPoll(count) {
            if (count >= 12) return; // max 6 menit polling
            setTimeout(function() {
                $.post(stmkRebuild.ajaxurl, { action: 'stmk_deploy_status', nonce: stmkRebuild.nonce })
                 .done(function(res) {
                     if (res.success) {
                         var d = res.data;
                         \$('#stmk-run-status').text('Deploy: ' + d.human_time + ' oleh ' + d.last_by);
                     }
                 });
                stmkPoll(count + 1);
            }, 30000); // poll setiap 30 detik
        }
    });
    ";
}

function stmk_get_settings_js() {
    return "
    jQuery(function($) {
        $('#stmk-test-github').on('click', function(e) {
            e.preventDefault();
            var \$btn = $(this);
            \$btn.prop('disabled', true).text('Testing...');
            \$('#stmk-test-result').text('');

            $.post(stmkRebuild.ajaxurl, {
                action: 'stmk_test_github',
                nonce:  stmkRebuild.nonce
            })
            .done(function(res) {
                \$btn.prop('disabled', false).text('Test Koneksi');
                var color = res.success ? '#16a34a' : '#dc2626';
                \$('#stmk-test-result').text(res.data.message).css('color', color);
            })
            .fail(function() {
                \$btn.prop('disabled', false).text('Test Koneksi');
                \$('#stmk-test-result').text('Gagal menghubungi server.').css('color', '#dc2626');
            });
        });
    });
    ";
}

// ── Footer text per role ─────────────────────────────────────────
add_filter( 'admin_footer_text', 'stmk_admin_footer_text' );
function stmk_admin_footer_text( $text ) {
    $role_names = [
        'stmk_humas'    => 'Staff Humas / Marketing',
        'stmk_akademik' => 'Staff Akademik',
        'stmk_pmb'      => 'Staff PMB',
        'stmk_pimpinan' => 'Pimpinan',
    ];
    $user = wp_get_current_user();
    foreach ( $role_names as $role => $label ) {
        if ( in_array( $role, (array) $user->roles, true ) )
            return '<span class="stmk-badge">STMK Trisakti</span> &nbsp; Masuk sebagai: <strong>' . esc_html( $label ) . '</strong>';
    }
    return $text;
}

// ── Admin color scheme per role ──────────────────────────────────
add_action( 'user_register',            'stmk_set_role_color_scheme' );
add_action( 'set_user_role',            'stmk_set_color_on_role_change', 10, 2 );
function stmk_set_role_color_scheme( $user_id ) {
    $user = get_userdata( $user_id );
    stmk_apply_color_scheme( $user_id, (array) $user->roles );
}
function stmk_set_color_on_role_change( $user_id, $role ) {
    stmk_apply_color_scheme( $user_id, [ $role ] );
}
function stmk_apply_color_scheme( $user_id, $roles ) {
    $scheme_map = [
        'stmk_pimpinan' => 'midnight',
        'stmk_humas'    => 'sunrise',
        'stmk_akademik' => 'ocean',
        'stmk_pmb'      => 'ectoplasm',
    ];
    foreach ( $scheme_map as $role => $scheme ) {
        if ( in_array( $role, $roles, true ) ) {
            update_user_meta( $user_id, 'admin_color', $scheme );
            return;
        }
    }
}

// ── Onboarding notice (hanya saat baru install) ──────────────────
add_action( 'admin_notices', 'stmk_onboarding_notice' );
function stmk_onboarding_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( get_option( 'stmk_setup_complete' ) ) return;
    $s = get_option( 'stmk_settings', [] );
    if ( ! empty( $s['github_token'] ) ) {
        update_option( 'stmk_setup_complete', 1 );
        return;
    }
    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>🎉 Plugin STMK Trisakti aktif!</strong> ';
    echo 'Sebelum digunakan, harap konfigurasi token GitHub di ';
    echo '<a href="' . esc_url( admin_url( 'options-general.php?page=stmk-settings' ) ) . '"><strong>Pengaturan Website STMK</strong></a>.';
    echo ' <a href="' . esc_url( add_query_arg( 'stmk_dismiss_setup', 1 ) ) . '" style="float:right;text-decoration:none;color:#64748b">Dismiss</a>';
    echo '</p></div>';
}

// Dismiss handler
add_action( 'admin_init', function() {
    if ( isset( $_GET['stmk_dismiss_setup'] ) && current_user_can( 'manage_options' ) )
        update_option( 'stmk_setup_complete', 1 );
} );
