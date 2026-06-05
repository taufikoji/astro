/**
 * WordPress REST API client untuk STMK Trisakti headless CMS.
 *
 * Semua fungsi mencoba fetch dari WordPress terlebih dahulu.
 * Jika WordPress tidak tersedia (timeout / error), kembalikan array/null
 * sehingga halaman Astro bisa menggunakan fallback data lokal.
 *
 * Env:
 *   WORDPRESS_API_URL  – base URL instalasi WP tanpa trailing slash
 *                        contoh: http://localhost/wordpress
 *                                https://trisaktimultimedia.ac.id
 */

const WP_BASE = (import.meta.env.WORDPRESS_API_URL as string | undefined)?.replace(/\/$/, '')
  ?? 'http://localhost/wordpress';

const API = `${WP_BASE}/wp-json/wp/v2`;

// ─────────────────────────────────────────────
// TYPES
// ─────────────────────────────────────────────

export interface WPMedia {
  id: number;
  source_url: string;
  alt_text: string;
  media_details?: { sizes?: Record<string, { source_url: string }> };
}

export interface WPTerm {
  id: number;
  name: string;
  slug: string;
}

export interface WPPost {
  id: number;
  slug: string;
  status: string;
  title: { rendered: string };
  content: { rendered: string };
  excerpt: { rendered: string };
  date: string;
  modified: string;
  featured_media: number;
  categories: number[];
  tags: number[];
  meta?: Record<string, string>;
  _embedded?: {
    'wp:featuredmedia'?: WPMedia[];
    'wp:term'?: WPTerm[][];
    author?: Array<{ name: string; avatar_urls?: Record<string, string> }>;
  };
}

export interface WPPage {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  excerpt: { rendered: string };
  date: string;
  meta?: Record<string, string>;
}

export interface WPDosen {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  meta: {
    gelar?: string;
    jabatan?: string;
    bidang_keahlian?: string;
    nidn?: string;
    prodi?: string;         // 'DKV' | 'TG' | 'Umum'
    email?: string;
    [key: string]: string | undefined;
  };
  _embedded?: { 'wp:featuredmedia'?: WPMedia[] };
}

export interface WPLowongan {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  date: string;
  meta: {
    perusahaan?: string;
    lokasi?: string;
    tipe?: string;          // 'Full-time' | 'Part-time' | 'Magang'
    deadline?: string;
    kontak_email?: string;
    prodi?: string;         // 'DKV' | 'TG' | 'Semua'
    persyaratan?: string;   // newline-separated list
    [key: string]: string | undefined;
  };
}

export interface WPTestimoni {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  meta: {
    posisi?: string;
    perusahaan?: string;
    prodi?: string;
    angkatan?: string;
    kutipan_singkat?: string;
    [key: string]: string | undefined;
  };
  _embedded?: { 'wp:featuredmedia'?: WPMedia[] };
}

export interface WPGaleri {
  id: number;
  slug: string;
  title: { rendered: string };
  content: { rendered: string };
  meta: {
    kategori?: string;       // 'Karya Tugas' | 'Karya Tugas Akhir' | 'Event Kampus' | 'Pameran'
    prodi?: string;
    konsentrasi?: string;
    tahun?: string;
    link_drive?: string;
    [key: string]: string | undefined;
  };
  _embedded?: { 'wp:featuredmedia'?: WPMedia[] };
}

// ─────────────────────────────────────────────
// INTERNAL FETCH HELPER
// ─────────────────────────────────────────────

async function wpFetch<T>(path: string, fallback: T): Promise<T> {
  try {
    const res = await fetch(`${API}/${path}`, {
      signal: AbortSignal.timeout(6000),
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) return fallback;
    return (await res.json()) as T;
  } catch {
    // WP tidak tersedia → gunakan fallback
    return fallback;
  }
}

// ─────────────────────────────────────────────
// PUBLIC API
// ─────────────────────────────────────────────

/** Cek apakah WordPress bisa dihubungi */
export async function isWordPressReachable(): Promise<boolean> {
  try {
    const res = await fetch(`${WP_BASE}/wp-json`, {
      signal: AbortSignal.timeout(3000),
      headers: { Accept: 'application/json' },
    });
    return res.ok;
  } catch {
    return false;
  }
}

// ── Berita (Posts) ──────────────────────────

export async function getPosts(params: Record<string, string> = {}): Promise<WPPost[]> {
  const q = new URLSearchParams({ _embed: '1', per_page: '20', status: 'publish', ...params });
  return wpFetch<WPPost[]>(`posts?${q}`, []);
}

export async function getPostBySlug(slug: string): Promise<WPPost | null> {
  const posts = await wpFetch<WPPost[]>(
    `posts?slug=${encodeURIComponent(slug)}&_embed=1&status=publish`,
    []
  );
  return posts[0] ?? null;
}

export async function getAllPostSlugs(): Promise<string[]> {
  const posts = await getPosts({ per_page: '100', fields: 'slug' });
  return posts.map(p => p.slug);
}

// ── WordPress Pages ──────────────────────────

export async function getPageBySlug(slug: string): Promise<WPPage | null> {
  const pages = await wpFetch<WPPage[]>(
    `pages?slug=${encodeURIComponent(slug)}&_embed=1`,
    []
  );
  return pages[0] ?? null;
}

// ── Dosen ─────────────────────────────────────

export async function getDosen(): Promise<WPDosen[]> {
  return wpFetch<WPDosen[]>(
    `dosen?_embed=1&per_page=100&status=publish&orderby=menu_order&order=asc`,
    []
  );
}

// ── Lowongan ──────────────────────────────────

export async function getLowongan(): Promise<WPLowongan[]> {
  return wpFetch<WPLowongan[]>(
    `lowongan?per_page=50&status=publish&orderby=date&order=desc`,
    []
  );
}

// ── Testimoni ─────────────────────────────────

export async function getTestimoni(): Promise<WPTestimoni[]> {
  return wpFetch<WPTestimoni[]>(
    `testimoni?_embed=1&per_page=50&status=publish`,
    []
  );
}

// ── Galeri ────────────────────────────────────

export async function getGaleri(): Promise<WPGaleri[]> {
  return wpFetch<WPGaleri[]>(
    `galeri?_embed=1&per_page=50&status=publish&orderby=date&order=desc`,
    []
  );
}

// ─────────────────────────────────────────────
// UTILITY HELPERS
// ─────────────────────────────────────────────

/** Ambil URL gambar unggulan dari post yang sudah di-embed */
export function getFeaturedImage(post: { _embedded?: { 'wp:featuredmedia'?: WPMedia[] } }): string | null {
  return post._embedded?.['wp:featuredmedia']?.[0]?.source_url ?? null;
}

/** Ambil nama kategori pertama dari post yang sudah di-embed */
export function getFirstCategory(post: WPPost): string {
  const terms = post._embedded?.['wp:term']?.[0];
  return terms?.[0]?.name ?? 'Umum';
}

/** Ambil nama penulis dari post yang sudah di-embed */
export function getAuthorName(post: WPPost): string {
  return post._embedded?.author?.[0]?.name ?? 'Redaksi STMK Trisakti';
}

/** Format tanggal ke locale Indonesia */
export function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

/** Hapus HTML tags dari string */
export function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

/** Pecah string persyaratan (newline-separated) jadi array */
export function parsePersyaratan(str: string = ''): string[] {
  return str.split('\n').map(s => s.trim()).filter(Boolean);
}

/** Inisial nama untuk avatar placeholder */
export function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(n => n[0]?.toUpperCase() ?? '').join('');
}
