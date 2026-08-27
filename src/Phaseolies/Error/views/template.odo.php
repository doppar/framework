<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>[[ $status_code ]] &middot; [[ $error_message ]]</title>

    <style>
        /* ============ TOKENS ============ */
        :root {
            --paper: #edece7;
            --ink: #1c1712;
            --ink-soft: #4a4238;
            --muted: #8a8274;
            --line: #ded8cc;
            --surface: #fbfaf6;
            --surface-2: #eae6dc;
            --signal: #c8481a;
            --signal-soft: rgba(200, 72, 26, .09);
            --signal-line: rgba(200, 72, 26, .35);
            --wire: #2f6f6b;
            --wire-soft: rgba(47, 111, 107, .09);
            --shadow: 0 1px 2px rgba(28, 23, 18, .04), 0 8px 24px -12px rgba(28, 23, 18, .10);

            --hl-tag: #a49c8c;
            --hl-variable: var(--signal);
            --hl-string: #4d6a8a;
            --hl-def: #6b5b95;
            --hl-mod: #a67c1e;
            --hl-keyword: #a3315c;
            --hl-literal: #5f7a3d;
            --hl-comment: var(--muted);
            --hl-number: var(--hl-mod);
            --hl-default: var(--ink-soft);
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --paper: #15110d;
                --ink: #ece7dd;
                --ink-soft: #c9c2b4;
                --muted: #93897a;
                --line: #332c22;
                --surface: #1c1712;
                --surface-2: #100d09;
                --signal: #e8703a;
                --signal-soft: rgba(232, 112, 58, .13);
                --signal-line: rgba(232, 112, 58, .4);
                --wire: #5cb8b0;
                --wire-soft: rgba(92, 184, 176, .12);
                --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 12px 28px -14px rgba(0, 0, 0, .6);

                --hl-tag: #6b6255;
                --hl-variable: var(--signal);
                --hl-string: #8fb4d9;
                --hl-def: #b4a4dd;
                --hl-mod: #d9a84a;
                --hl-keyword: #e0698f;
                --hl-literal: #a3c274;
                --hl-comment: var(--muted);
                --hl-number: var(--hl-mod);
                --hl-default: var(--ink-soft);
            }
        }

        :root[data-theme="dark"] {
            --paper: #15110d;
            --ink: #ece7dd;
            --ink-soft: #c9c2b4;
            --muted: #93897a;
            --line: #332c22;
            --surface: #1c1712;
            --surface-2: #100d09;
            --signal: #e8703a;
            --signal-soft: rgba(232, 112, 58, .13);
            --signal-line: rgba(232, 112, 58, .4);
            --wire: #5cb8b0;
            --wire-soft: rgba(92, 184, 176, .12);
            --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 12px 28px -14px rgba(0, 0, 0, .6);

            --hl-tag: #6b6255;
            --hl-variable: var(--signal);
            --hl-string: #8fb4d9;
            --hl-def: #b4a4dd;
            --hl-mod: #d9a84a;
            --hl-keyword: #e0698f;
            --hl-literal: #a3c274;
            --hl-comment: var(--muted);
            --hl-number: var(--hl-mod);
            --hl-default: var(--ink-soft);
        }

        /* ============ RESET / BASE ============ */
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--paper);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            background-image:
                radial-gradient(640px 460px at 100% 0%, var(--signal-soft), transparent 62%),
                radial-gradient(560px 420px at 0% 100%, var(--wire-soft), transparent 62%),
                linear-gradient(color-mix(in srgb, var(--ink) 5%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in srgb, var(--ink) 5%, transparent) 1px, transparent 1px);
            background-position: top right, bottom left, center, center;
            background-repeat: no-repeat, no-repeat, repeat, repeat;
            background-size: auto, auto, 44px 44px, 44px 44px;
            background-attachment: fixed, fixed, fixed, fixed;
        }

        code,
        pre,
        .mono {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        }

        ::selection {
            background: var(--signal-soft);
            color: var(--ink);
        }

        a {
            color: var(--wire);
        }

        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--line);
            border-radius: 99px;
        }

        button,
        input {
            font: inherit;
            color: inherit;
        }

        button {
            cursor: pointer;
        }

        :focus-visible {
            outline: 2px solid var(--signal);
            outline-offset: 2px;
            border-radius: 4px;
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
        }

        /* ============ LAYOUT ============ */
        .wrap {
            max-width: 1040px;
            margin: 0 auto;
            padding: 0 20px;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rise-1 {
            animation: rise .38s ease both;
        }

        .rise-2 {
            animation: rise .38s .05s ease both;
        }

        .rise-3 {
            animation: rise .38s .10s ease both;
        }

        .rise-4 {
            animation: rise .38s .15s ease both;
        }

        .rise-5 {
            animation: rise .38s .20s ease both;
        }

        /* ============ COMMAND BAR ============ */
        .bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 0;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px 6px 10px;
            border-radius: 7px;
            background: var(--signal-soft);
            border: 1px solid var(--signal-line);
            color: var(--signal);
            font-weight: 600;
            font-size: 12.5px;
            letter-spacing: .01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 62vw;
        }

        .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            flex: none;
            animation: blip 2s ease infinite;
        }

        @keyframes blip {

            0%,
            100% {
                opacity: .9;
                transform: scale(1);
            }

            50% {
                opacity: .35;
                transform: scale(.65);
            }
        }

        .bar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: none;
        }

        .chipset {
            display: flex;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }

        .chipset span {
            padding: 6px 10px;
            border-right: 1px solid var(--line);
            color: var(--muted);
        }

        .chipset span:last-child {
            border-right: none;
        }

        .chipset b {
            color: var(--ink);
            font-weight: 600;
        }

        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: transparent;
            color: var(--ink-soft);
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .icon-btn:hover {
            background: var(--surface-2);
            color: var(--ink);
        }

        .icon-btn svg {
            width: 15px;
            height: 15px;
        }

        .icon-btn.ok {
            color: var(--wire);
            border-color: var(--wire);
            background: var(--wire-soft);
        }

        /* ============ HERO ============ */
        .hero {
            position: relative;
            padding: 8px 0 22px;
        }

        .kicker {
            position: relative;
            margin: 0 0 10px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--signal);
        }

        .headline {
            position: relative;
            margin: 0 0 18px;
            font-size: clamp(21px, 3vw, 30px);
            font-weight: 700;
            letter-spacing: -.015em;
            line-height: 1.25;
            max-width: 60ch;
            text-wrap: balance;
            color: var(--ink);
            word-wrap: break-word;
        }

        .stat-row {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 7px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--line);
            color: var(--ink-soft);
            font-variant-numeric: tabular-nums;
        }

        .chip.on-signal {
            background: var(--signal-soft);
            border-color: var(--signal-line);
            color: var(--signal);
        }

        .chip.on-wire {
            background: var(--wire-soft);
            border-color: color-mix(in srgb, var(--wire) 45%, transparent);
            color: var(--wire);
        }

        .meta-time {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        /* ============ REQUEST LINE ============ */
        .request-line {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 9px 10px 9px 12px;
            margin-bottom: 22px;
            box-shadow: var(--shadow);
        }

        .method {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: .04em;
            padding: 4px 8px;
            border-radius: 6px;
            flex: none;
            background: var(--wire-soft);
            color: var(--wire);
        }

        .url {
            flex: 1;
            min-width: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            color: var(--ink-soft);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ============ PANEL ============ */
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .panel-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
            background: var(--surface-2);
        }

        .panel-head h2 {
            margin: 0;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: -.005em;
        }

        .win-dots {
            display: flex;
            gap: 6px;
            flex: none;
        }

        .win-dots i {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: block;
        }

        .win-dots i:nth-child(1) {
            background: color-mix(in srgb, var(--signal) 70%, var(--surface-2));
        }

        .win-dots i:nth-child(2) {
            background: color-mix(in srgb, var(--hl-mod) 70%, var(--surface-2));
        }

        .win-dots i:nth-child(3) {
            background: color-mix(in srgb, var(--wire) 70%, var(--surface-2));
        }

        .file-path {
            flex: 1;
            min-width: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .count-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 99px;
            padding: 2px 8px;
            flex: none;
        }

        /* ============ CODE BLOCK ============ */
        .code {
            margin: 0;
            padding: 4px 0;
            overflow-x: auto;
            background: var(--surface-2);
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        }

        .code-line,
        .code-line-error {
            display: flex;
            width: 100%;
            font-size: 12.5px;
            line-height: 1.65;
            white-space: pre;
        }

        .code-line-error {
            background: var(--signal-soft);
            border-left: 2px solid var(--signal);
        }

        .code-line-number {
            width: 42px;
            flex: none;
            text-align: right;
            padding-right: 16px;
            color: var(--muted);
            opacity: .7;
            user-select: none;
        }

        .code-line-content {
            flex: 1;
            padding-right: 16px;
            color: var(--hl-default);
        }

        .frame-no-code {
            padding: 16px;
            font-size: 12.5px;
            color: var(--muted);
        }

        .text-hl-tag {
            color: var(--hl-tag);
        }

        .text-hl-variable {
            color: var(--hl-variable);
            font-weight: 600;
        }

        .text-hl-string {
            color: var(--hl-string);
        }

        .text-hl-definition {
            color: var(--hl-def);
            font-weight: 600;
        }

        .text-hl-modifier {
            color: var(--hl-mod);
        }

        .text-hl-keyword {
            color: var(--hl-keyword);
            font-weight: 600;
        }

        .text-hl-literal {
            color: var(--hl-literal);
        }

        .text-hl-comment {
            color: var(--hl-comment);
            font-style: italic;
        }

        .text-hl-number {
            color: var(--hl-number);
        }

        .text-hl-default {
            color: var(--hl-default);
        }

        /* ============ STACK TRACE / SIGNAL RAIL ============ */
        .trace-tools {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .filter-input {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 12px;
            color: var(--ink);
            width: 150px;
        }

        .filter-input::placeholder {
            color: var(--muted);
        }

        .toggle-btn {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--ink-soft);
            transition: background .15s ease, color .15s ease;
            white-space: nowrap;
        }

        .toggle-btn:hover {
            background: var(--surface-2);
        }

        .toggle-btn[aria-pressed="true"] {
            background: var(--wire-soft);
            color: var(--wire);
            border-color: color-mix(in srgb, var(--wire) 45%, transparent);
        }

        .rail {
            position: relative;
            padding: 4px 16px 10px 46px;
        }

        .rail::before {
            content: "";
            position: absolute;
            left: 19px;
            top: 30px;
            bottom: 30px;
            width: 2px;
            background: linear-gradient(var(--signal), var(--line) 65%);
        }

        .frame {
            position: relative;
            border-bottom: 1px solid var(--line);
        }

        .frame:last-child {
            border-bottom: none;
        }

        .frame[data-vendor="1"] {
            opacity: .78;
        }

        .rail.hide-vendor .frame[data-vendor="1"] {
            display: none;
        }

        .rail.filtering .frame[data-hide="1"] {
            display: none;
        }

        .node {
            position: absolute;
            left: -35px;
            top: 14px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--surface);
            border: 2px solid var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10px;
            font-weight: 700;
            color: var(--ink);
        }

        .frame:first-child .node {
            background: var(--signal);
            border-color: var(--signal);
            color: var(--surface);
            box-shadow: 0 0 0 4px var(--signal-soft);
        }

        .frame-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            padding: 13px 4px 13px 0;
            min-height: 56px;
        }

        .frame-toggle:hover {
            background: color-mix(in srgb, var(--ink) 3%, transparent);
        }

        .frame-main {
            flex: 1;
            min-width: 0;
        }

        .frame-sig {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .frame-file {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vendor-tag {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 1px 5px;
            flex: none;
        }

        .chev {
            width: 14px;
            height: 14px;
            color: var(--muted);
            flex: none;
            transition: transform .18s ease;
        }

        .frame-toggle[aria-expanded="true"] .chev,
        .kv-toggle[aria-expanded="true"] .chev {
            transform: rotate(180deg);
        }

        .frame-body {
            display: none;
            border-top: 1px solid var(--line);
            background: var(--surface-2);
        }

        .frame-body .code {
            background: transparent;
        }

        /* ============ DUAL COLUMN ============ */
        .dual-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .dual-col .panel {
            margin-bottom: 0;
        }

        @media (max-width: 760px) {
            .dual-col {
                grid-template-columns: 1fr;
            }
        }

        .kv-toggle {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            padding: 0;
        }

        .kv-panel {
            display: none;
        }

        .kv-list {
            padding: 6px 16px 14px;
        }

        .kv-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 6px 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
        }

        .kv-key {
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .03em;
            font-size: 10.5px;
            flex: none;
        }

        .kv-dots {
            flex: 1;
            border-bottom: 1px dashed var(--line);
            margin-bottom: 4px;
            min-width: 12px;
        }

        .kv-val {
            color: var(--ink-soft);
            text-align: right;
            max-width: 60%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 34px 16px;
            color: var(--muted);
            gap: 8px;
        }

        .empty-state svg {
            width: 28px;
            height: 28px;
            opacity: .55;
        }

        .empty-state span {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10.5px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .json-body {
            margin: 0;
            padding: 14px 16px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.6;
            color: var(--ink-soft);
            background: var(--surface-2);
        }

        /* ============ ROUTING ============ */
        .routing-grid {
            padding: 18px 16px 4px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px 28px;
        }

        .field-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .route-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 5px 0;
        }

        .route-key {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            flex: none;
        }

        .route-dots {
            flex: 1;
            border-bottom: 1px dashed var(--line);
            margin-bottom: 4px;
        }

        .route-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-soft);
            word-break: break-all;
        }

        .route-none {
            font-size: 12px;
            font-style: italic;
            color: var(--muted);
            padding-bottom: 14px;
        }

        /* ============ INFO GRID ============ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        @media (max-width: 760px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-card {
            padding: 16px;
        }

        .info-card .field-label {
            margin-bottom: 12px;
        }

        .info-row {
            margin-bottom: 12px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-row .k {
            font-size: 10.5px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }

        .info-row .v {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            color: var(--ink-soft);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .foot {
            text-align: center;
            padding: 18px 0 36px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10px;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--muted);
        }
    </style>
</head>

<body>
    <div class="wrap">

        <header class="bar rise-1">
            <span class="pill"><i class="dot"></i>[[ $exception_class ]]</span>
            <div class="bar-right">
                <div class="chipset">
                    <span>DOPPAR <b>[[ $doppar_version ]]</b></span>
                    <span>PHP <b>[[ $php_version ]]</b></span>
                </div>
                <button class="icon-btn" id="themeBtn" aria-label="Toggle color theme">
                    <svg id="iSun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                    <svg id="iMoon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
                </button>
                <button class="icon-btn" id="copyReportBtn" aria-label="Copy exception report as Markdown" title="Copy report as Markdown">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.1c0-1.13.845-2.1 1.976-2.19.373-.03.748-.06 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.1c0-1.13-.845-2.1-1.976-2.19a48 48 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.25 2.25 0 0 0 15 2.25h-1.5a2.25 2.25 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z"/></svg>
                </button>
            </div>
        </header>

        <section class="hero rise-2">
            <p class="kicker">Uncaught &mdash; [[ $exception_class ]]</p>
            <h1 class="headline">[[ $error_message ]]</h1>
            <div class="stat-row">
                <span class="chip on-signal">[[ $status_code ]]</span>
                <span class="chip on-wire">[[ $request_method ]]</span>
                <span class="chip">[[ count($traces) ]] frames</span>
                <span class="meta-time">[[ $timestamp ]]</span>
            </div>
        </section>

        <div class="request-line rise-2">
            <span class="method">[[ $request_method ]]</span>
            <span class="url">[[ $request_url ]]</span>
            <button class="icon-btn" id="copyUrlBtn" aria-label="Copy request URL" title="Copy URL">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m-6 12h8a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2Z"/></svg>
            </button>
        </div>

        <main>
            <!-- SOURCE -->
            <section class="panel rise-3">
                <div class="panel-head">
                    <span class="win-dots"><i></i><i></i><i></i></span>
                    <span class="file-path">[[ $error_file ]]</span>
                    <span class="chip on-signal">Line [[ $error_line ]]</span>
                </div>
                <div class="code">[[! $contents !]]</div>
            </section>

            <!-- STACK TRACE -->
            <section class="panel rise-4">
                <div class="panel-head">
                    <h2>Stack Trace</h2>
                    <span class="count-badge" id="frameCount">[[ count($traces) ]] frames</span>
                    <div class="trace-tools">
                        <input type="search" class="filter-input" id="frameFilter" placeholder="Filter frames&hellip;" aria-label="Filter stack frames">
                        <button class="toggle-btn" id="vendorToggle" aria-pressed="false">Hide vendor</button>
                        <button class="toggle-btn" id="expandAllBtn">Expand all</button>
                    </div>
                </div>
                <div id="rail" class="rail">
                    #include('trace-frames', ['traces' => $traces])
                </div>
            </section>

            <!-- HEADERS + BODY -->
            <div class="dual-col rise-5">
                <section class="panel">
                    #include('template-headers', ['headers' => $headers])
                </section>

                <section class="panel">
                    <div class="panel-head" style="border-bottom:none;">
                        <h2>Request Body</h2>
                        <span class="count-badge">
                            #if (!empty($request_body))
                            payload
                            #else
                            empty
                            #endif
                        </span>
                    </div>
                    #if (!empty($request_body))
                    <pre class="json-body"><code>[[ json_encode($request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ]]</code></pre>
                    #else
                    <div style="border-top:1px solid var(--line)">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4"/></svg>
                            <span>No payload on this request</span>
                        </div>
                    </div>
                    #endif
                </section>
            </div>

            <!-- ROUTING -->
            <section class="panel rise-5">
                <div class="panel-head">
                    <h2>Routing</h2>
                </div>
                <div class="routing-grid">
                    <div>
                        <div class="field-label">Route</div>
                        <div class="route-row"><span class="route-key">Name</span><span class="route-dots"></span><span class="route-val">[[ $current_route_name ?? 'unnamed_route' ]]</span></div>
                        <div class="route-row">
                            <span class="route-key">Action</span><span class="route-dots"></span>
                            #if (!empty($current_route_action))
                            <span class="route-val">[[ $current_route_action ]]</span>
                            #else
                            <span class="route-val" style="font-style:italic;font-weight:400;color:var(--muted)">Closure</span>
                            #endif
                        </div>
                    </div>
                    <div>
                        <div class="field-label">Middleware ([[ count($current_middleware ?? []) ]])</div>
                        #if (!empty($current_middleware))
                        #foreach(($current_middleware ?? []) as $index => $mw)
                        <div class="route-row"><span class="route-key">[[ $index + 1 ]]</span><span class="route-dots"></span><span class="route-val">[[ $mw ]]</span></div>
                        #endforeach
                        #else
                        <div class="route-none">No middleware</div>
                        #endif
                    </div>
                    <div>
                        <div class="field-label">Route Parameters</div>
                        #if (!empty($current_route_params))
                        #foreach ($current_route_params as $key => $val)
                        <div class="route-row"><span class="route-key">[[ $key ]]</span><span class="route-dots"></span><span class="route-val">[[ $val ]]</span></div>
                        #endforeach
                        #else
                        <div class="route-none">No parameters</div>
                        #endif
                    </div>
                </div>
            </section>

            <!-- INFO GRID -->
            <div class="info-grid rise-5">
                <section class="panel info-card">
                    <div class="field-label">System</div>
                    <div class="info-row"><div class="k">Server</div><div class="v">[[ $server_software ]]</div></div>
                    <div class="info-row"><div class="k">Platform</div><div class="v">[[ $platform ]]</div></div>
                </section>
                <section class="panel info-card">
                    <div class="field-label">Memory</div>
                    <div class="info-row"><div class="k">Current Usage</div><div class="v">[[ number_format($memory_usage / 1024 / 1024, 2) ]] MB</div></div>
                    <div class="info-row"><div class="k">Peak Usage</div><div class="v">[[ number_format($peack_memory_usage / 1024 / 1024, 2) ]] MB</div></div>
                </section>
                <section class="panel info-card">
                    <div class="field-label">User</div>
                    #if ($user_info)
                    <div class="info-row"><div class="k">ID</div><div class="v">[[ $user_info['id'] ]]</div></div>
                    <div class="info-row"><div class="k">Email</div><div class="v">[[ $user_info['email'] ]]</div></div>
                    #else
                    <div class="empty-state" style="padding:6px 0 0;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <span>No user</span>
                    </div>
                    #endif
                </section>
            </div>

            <div class="foot">Doppar Framework &middot; Request Diagnostic</div>
        </main>
    </div>

    <textarea id="mdContent" style="position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;">[[ $md_content ]]</textarea>

    <script>
        (function () {
            var KEY = 'doppar-error-theme';
            function getTheme() { try { return localStorage.getItem(KEY) || 'system'; } catch (e) { return 'system'; } }
            function apply(t) {
                var root = document.documentElement;
                if (t === 'system') root.removeAttribute('data-theme');
                else root.setAttribute('data-theme', t);
                var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.getElementById('iSun').style.display = dark ? 'block' : 'none';
                document.getElementById('iMoon').style.display = dark ? 'none' : 'block';
            }
            apply(getTheme());
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (getTheme() === 'system') apply('system');
            });
            document.getElementById('themeBtn').addEventListener('click', function () {
                var current = document.documentElement.getAttribute('data-theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                var next = current === 'dark' ? 'light' : 'dark';
                try { localStorage.setItem(KEY, next); } catch (e) {}
                apply(next);
            });

            function flashCopy(btn, ok) {
                var orig = btn.innerHTML;
                btn.classList.toggle('ok', ok);
                if (ok) btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                setTimeout(function () { btn.innerHTML = orig; btn.classList.remove('ok'); }, 1600);
            }
            function copyText(text, btn) {
                function done(ok) { flashCopy(btn, ok); }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
                } else {
                    var el = document.createElement('textarea');
                    el.value = text; el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
                    document.body.appendChild(el); el.focus(); el.select();
                    try { document.execCommand('copy'); done(true); } catch (e) { done(false); }
                    document.body.removeChild(el);
                }
            }
            document.getElementById('copyUrlBtn').addEventListener('click', function () {
                copyText('[[ $request_url ]]', this);
            });
            document.getElementById('copyReportBtn').addEventListener('click', function () {
                var md = document.getElementById('mdContent');
                copyText(md ? md.value : '', this);
            });

            var rail = document.getElementById('rail');
            rail.addEventListener('click', function (e) {
                var t = e.target.closest('[data-toggle]');
                if (!t) return;
                var id = t.getAttribute('data-toggle');
                var body = rail.querySelector('[data-body="' + id + '"]');
                if (!body) return;
                var open = body.style.display === 'block';
                body.style.display = open ? 'none' : 'block';
                t.setAttribute('aria-expanded', String(!open));
            });
            rail.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var t = e.target.closest('[data-toggle]');
                if (t) { e.preventDefault(); t.click(); }
            });

            var expandBtn = document.getElementById('expandAllBtn');
            var allOpen = false;
            expandBtn.addEventListener('click', function () {
                allOpen = !allOpen;
                rail.querySelectorAll('[data-body]').forEach(function (b) { b.style.display = allOpen ? 'block' : 'none'; });
                rail.querySelectorAll('[data-toggle]').forEach(function (h) { h.setAttribute('aria-expanded', String(allOpen)); });
                expandBtn.textContent = allOpen ? 'Collapse all' : 'Expand all';
            });

            var vendorBtn = document.getElementById('vendorToggle');
            vendorBtn.addEventListener('click', function () {
                var on = vendorBtn.getAttribute('aria-pressed') === 'true';
                vendorBtn.setAttribute('aria-pressed', String(!on));
                rail.classList.toggle('hide-vendor', !on);
                vendorBtn.textContent = !on ? 'Show vendor' : 'Hide vendor';
                updateCount();
            });

            var filterInput = document.getElementById('frameFilter');
            filterInput.addEventListener('input', function () {
                var q = filterInput.value.trim().toLowerCase();
                rail.classList.toggle('filtering', q.length > 0);
                rail.querySelectorAll('.frame').forEach(function (f) {
                    var hay = f.getAttribute('data-search') || '';
                    f.setAttribute('data-hide', (q && hay.indexOf(q) === -1) ? '1' : '0');
                });
                updateCount();
            });

            function updateCount() {
                var visible = Array.prototype.filter.call(rail.querySelectorAll('.frame'), function (f) {
                    var vendorHidden = rail.classList.contains('hide-vendor') && f.getAttribute('data-vendor') === '1';
                    var filterHidden = rail.classList.contains('filtering') && f.getAttribute('data-hide') === '1';
                    return !vendorHidden && !filterHidden;
                });
                var label = visible.length + ' frames';
                document.getElementById('frameCount').textContent = label;
            }

            function makeKvToggle(btn, panel) {
                if (!btn || !panel) return;
                btn.addEventListener('click', function () {
                    var open = panel.style.display === 'block';
                    panel.style.display = open ? 'none' : 'block';
                    btn.setAttribute('aria-expanded', String(!open));
                });
            }
            makeKvToggle(document.getElementById('headersToggle'), document.getElementById('headersPanel'));
        })();
    </script>
</body>

</html>
