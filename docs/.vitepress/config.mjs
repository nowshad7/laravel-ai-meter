import { defineConfig } from 'vitepress'

// Project site served from https://nowshad7.github.io/laravel-ai-meter/
export default defineConfig({
  title: 'Laravel AI Meter',
  description: 'Self-hosted metering + budget enforcement for AI/LLM & agent calls in Laravel.',
  base: '/laravel-ai-meter/',
  lastUpdated: true,
  cleanUrls: true,
  head: [['meta', { name: 'theme-color', content: '#10b981' }]],
  themeConfig: {
    logo: '/logo.svg',
    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Changelog', link: 'https://github.com/nowshad7/laravel-ai-meter/blob/main/CHANGELOG.md' },
      { text: 'Packagist', link: 'https://packagist.org/packages/nsd7/laravel-ai-meter' },
    ],
    sidebar: {
      '/guide/': [
        {
          text: 'Getting started',
          items: [
            { text: 'Introduction', link: '/guide/getting-started' },
            { text: 'Configuration', link: '/guide/configuration' },
          ],
        },
        {
          text: 'Usage',
          items: [
            { text: 'Recording calls', link: '/guide/recording' },
            { text: 'Auto-instrumentation', link: '/guide/auto-instrumentation' },
            { text: 'Budgets & alerts', link: '/guide/budgets' },
            { text: 'Agent runs', link: '/guide/runs' },
            { text: 'Dashboard', link: '/guide/dashboard' },
            { text: 'Commands', link: '/guide/commands' },
          ],
        },
      ],
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/nowshad7/laravel-ai-meter' },
    ],
    search: { provider: 'local' },
    editLink: {
      pattern: 'https://github.com/nowshad7/laravel-ai-meter/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Robiul Hasan Nowshad',
    },
  },
})
