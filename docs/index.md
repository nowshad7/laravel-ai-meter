---
layout: home
hero:
  name: Laravel AI Meter
  text: Meter & govern your AI spend
  tagline: Trace every LLM / agent call, attribute token cost to a user or tenant, watch it in a built-in dashboard, and block runaway spend before it happens — self-hosted, no external service.
  image:
    src: /logo.svg
    alt: AI Meter
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: GitHub
      link: https://github.com/nowshad7/laravel-ai-meter
features:
  - icon: 📊
    title: Built-in dashboard
    details: Spend today / this month / all-time, a spend-over-time chart, cost by model & provider, top scopes, a budgets page, and a filterable call log — Blade + Tailwind, dark mode.
  - icon: 🛑
    title: Budget enforcement
    details: Spend ceilings per user / tenant / global, over run / day / month / total, with block (HTTP 402) or alert actions. Stop a runaway agent mid-loop.
  - icon: 🔔
    title: Budget alerts
    details: Mail / Slack notifications the moment a scope crosses 80% / 100% of a budget (configurable), once per window — or listen for the event yourself.
  - icon: 🧾
    title: Cost attribution
    details: Every call priced from a bundled, overridable price book. Unknown models use a configurable fallback, never silently $0. Refresh prices with a command.
  - icon: 🔌
    title: Capture anything
    details: A reliable Meter::log() / recordPrism() API, plus opt-in auto-instrumentation for the Laravel AI SDK, Neuron AI, and Prism.
  - icon: 🗄️
    title: Self-hosted & scalable
    details: Stores in your database (MySQL, PostgreSQL, SQLite, SQL Server). Enforce budgets off per-request Redis counters at high volume. No accounts, no egress.
---
