const fs = require('fs');
const path = require('path');
// Note: This script requires 'xml2js' and 'turndown' packages.
// Run: npm install xml2js turndown
const xml2js = require('xml2js');
const TurndownService = require('turndown');

const turndownService = new TurndownService({
  headingStyle: 'atx',
  codeBlockStyle: 'fenced'
});

// Run this script via: node scripts/wp-migrator.js path/to/export.xml
const xmlFilePath = process.argv[2];

if (!xmlFilePath) {
  console.error("Harap masukkan path file XML WordPress.");
  console.error("Contoh: node scripts/wp-migrator.js export.xml");
  process.exit(1);
}

const outputDir = path.join(__dirname, '../src/content/berita');

if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

fs.readFile(xmlFilePath, 'utf-8', (err, data) => {
  if (err) {
    console.error("Gagal membaca file:", err);
    return;
  }

  xml2js.parseString(data, (err, result) => {
    if (err) {
      console.error("Gagal parse XML:", err);
      return;
    }

    const channel = result.rss.channel[0];
    const items = channel.item || [];
    
    let count = 0;

    items.forEach(item => {
      // Check if it's a post and published
      const postType = item['wp:post_type']?.[0];
      const status = item['wp:status']?.[0];
      
      if (postType !== 'post' || status !== 'publish') {
        return;
      }

      const title = item.title[0];
      const pubDate = new Date(item.pubDate[0] || item['wp:post_date'][0]).toISOString().split('T')[0];
      const creator = item['dc:creator']?.[0] || 'Admin';
      const rawContent = item['content:encoded']?.[0] || '';
      
      // Convert HTML content to Markdown
      const markdownContent = turndownService.turndown(rawContent);

      // Create valid filename slug
      let slug = item['wp:post_name']?.[0];
      if (!slug) {
        slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
      }

      // We will look for an image URL in the content or meta if needed,
      // but for simplicity, we leave image empty or extract first image url
      let image = '';
      const imgMatch = rawContent.match(/<img[^>]+src="([^">]+)"/);
      if (imgMatch) {
        // Just keeping a reference, ideally we should download it.
        // The user can manually fix images or we can add a downloader.
        image = imgMatch[1];
      }

      const frontmatter = `---
title: "${title.replace(/"/g, '\\"')}"
date: ${pubDate}
author: "${creator.replace(/"/g, '\\"')}"
${image ? `image: "${image}"` : ''}
---
`;

      const finalContent = frontmatter + '\n' + markdownContent;
      
      const filePath = path.join(outputDir, `${slug}.md`);
      fs.writeFileSync(filePath, finalContent);
      console.log(`Dibuat: ${slug}.md`);
      count++;
    });

    console.log(`\nSelesai! Berhasil memigrasi ${count} artikel berita.`);
  });
});
