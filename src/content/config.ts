import { defineCollection, z } from 'astro:content';

const blog = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    description: z.string(),
    date: z.string(),
    author: z.string().default('Benjamin'),
    tags: z.array(z.string()).default([]),
    keywords: z.array(z.string()).default([]),
    image: z.string().optional(),
  }),
});

export const collections = { blog };
