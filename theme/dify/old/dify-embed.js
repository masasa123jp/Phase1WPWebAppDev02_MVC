// dify-embed.js

/** iframe 方式：指定の container に公式画面を埋め込み */
export function mountDifyIframe({ container, appUrl, height = '70vh' }) {
  if (!container) throw new Error('container が未指定です');
  container.innerHTML = '';
  const iframe = document.createElement('iframe');
  iframe.src = appUrl;
  iframe.title = 'Dify Chat';
  iframe.style.width = '100%';
  iframe.style.height = height;
  iframe.style.border = '0';
  iframe.allow = 'clipboard-read; clipboard-write; microphone; camera; fullscreen';
  container.appendChild(iframe);
}

/** script 方式：右下にバブル（オーバーレイ）を表示 */
export function mountDifyScript({
  baseUrl = 'https://udify.app',
  token,
  systemVariables = {},
  userVariables = {}
}) {
  if (!token) throw new Error('script 方式には埋め込みトークンが必要です（管理画面 Embedded で取得）');
  // 1) 先に config を定義（docs の要点）:contentReference[oaicite:18]{index=18}
  window.difyChatbotConfig = {
    token,
    baseUrl,
    // ユーザー固有の識別や前提情報を渡したい時に使う
    systemVariables,
    userVariables
    // 必要に応じて他のオプション（位置/テーマ/自動オープン等）が追加される場合があります
  };
  // 2) 後からスクリプトを読み込む
  return new Promise((resolve, reject) => {
    // 既存の埋め込みスクリプトを削除（再初期化対応）
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

/** （任意）バブルを開く試行 */
export function openBubbleIfAny() {
  // Dify 側の実装に依存するため確実ではありませんが、
  // 通常はバブルが自動生成されるため、クリックを疑似発火します。
  const bubble = document.getElementById('dify-chatbot-bubble') || document.querySelector('[data-dify]');
  if (bubble) bubble.click();
}
