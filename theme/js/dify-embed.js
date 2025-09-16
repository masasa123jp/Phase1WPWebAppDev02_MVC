// js/dify-embed.js
// 目的：公式の Dify 画面を 1) iframe 方式、2) script 方式（バブル）で埋め込む。
// ポイント：script 方式は config を先に定義 → embed.min.js を読み込む（公式に準拠）。:contentReference[oaicite:12]{index=12}

/** iframe 方式：指定コンテナに Dify をそのまま埋め込み */
export function mountDifyIframe({ container, appUrl, height = '100%' }) {
  if (!container) throw new Error('container が未指定です');
  container.innerHTML = '';

  const iframe = document.createElement('iframe');
  iframe.src = appUrl;
  iframe.title = 'Dify Chat';
  iframe.style.width = '100%';
  iframe.style.height = height;
  iframe.style.border = '0';
  iframe.style.borderRadius = 'var(--border-radius)';
  iframe.allow = 'clipboard-read; clipboard-write; microphone; camera; fullscreen';

  container.appendChild(iframe);
}

/** script 方式：右下にバブル（オーバーレイ）を生成 */
export function mountDifyScript({
  baseUrl = 'https://udify.app',
  token,
  systemVariables = {},
  userVariables = {}
}) {
  if (!token) throw new Error('script 方式には埋め込みトークン（Embed Token）が必要です');

  // 1) 先に config をグローバルへ（順序が重要）
  window.difyChatbotConfig = {
    token,
    baseUrl,
    systemVariables,
    userVariables
    // 必要に応じて他オプション（テーマ/自動オープン等）は今後拡張
  };

  // 2) embed.min.js を動的読込
  return new Promise((resolve, reject) => {
    // 再初期化時の掃除
    document.querySelectorAll('script[data-dify-embed="true"]').forEach(s => s.remove());

    const s = document.createElement('script');
    s.src = `${baseUrl.replace(/\/$/, '')}/embed.min.js`;
    s.async = true;
    s.dataset.difyEmbed = 'true';
    s.onload = () => resolve(true);
    s.onerror = () => reject(new Error('embed.min.js の読み込みに失敗しました'));
    document.head.appendChild(s);
  });
}

/** バブルを開く補助（存在すればクリックを疑似発火） */
export function openBubbleIfAny() {
  const bubble = document.getElementById('dify-chatbot-bubble') || document.querySelector('[data-dify]');
  if (bubble) bubble.click();
}
