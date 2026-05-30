import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const beritaCollection = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./src/content/berita" }),
  schema: z.object({
    title: z.string(),
    date: z.string().or(z.date()),
    author: z.string().default('Admin STMK Trisakti'),
    category: z.string().default('Pengumuman'),
    tags: z.array(z.string()).optional(),
    image: z.string().optional(),
  }),
});

export const collections = {
  berita: beritaCollection,
};
