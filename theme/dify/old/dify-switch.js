// dify-switch.js
import { mountDifyIframe, mountDifyScript, openBubbleIfAny } from './dify-embed.js';
import { mountCustomChat } from './custom-chat-ui.js';

/** ========= 切替用の設定 ========= */
const DEFAULT_USE_DIFY = true;          // ← ここを true/false で切替（URLクエリがあればそちら優先）
const EMBED_METHOD = 'iframe';          // 'iframe' | 'script'
const DIFY_APP_URL = 'https://udify.app/chat/6zuAJFJ2V2iGPknI'; // 公式画面のURL（既存 dify.html を踏襲）:contentReference[oaicite:12]{index=12}
const DIFY_BASE_URL = 'https://udify.app';                         // script 方式で使うベースURL
const EMBED_TOKEN = '';                  // script 方式用。管理画面の「Embedded → Script tag」で取得。:contentReference[oaicite:13]{index=13}

// （任意）ユーザー情報の受け渡し。script方式のみ有効
const SYSTEM_VARIABLES = { user_id: '123' };
const USER_VARIABLES   = { user_name: 'ユーザー名' };

/** ========= 起動ユーティリティ ========= */
// URLの ?embed=true|false で上書き
function readEmbedFlagFromQuery() {
  const url = new URL(location.href);
  const v = url.searchParams.get('embed');
  if (v === null) return null;
  return /^(1|true|yes|on)$/i.test(v);
}
function $(id){ return document.getElementById(id); }

document.addEventListener('DOMContentLoaded', async () => {
  // どちらを使うか決定
  const override = readEmbedFlagFromQuery();
  const USE_DIFY_EMBED = (override === null) ? DEFAULT_USE_DIFY : override;

  const embedArea  = $('embed-area');
  const embedHost  = $('embed-host');
  const customArea = $('custom-area');
  const customHost = $('custom-host');

  // 表示切替
  embedArea.style.display  = USE_DIFY_EMBED ? 'block' : 'none';
  customArea.style.display = USE_DIFY_EMBED ? 'none'  : 'block';

  // マウント
  if (USE_DIFY_EMBED) {
    if (EMBED_METHOD === 'script' && EMBED_TOKEN) {
      await mountDifyScript({
        baseUrl: DIFY_BASE_URL,
        token: EMBED_TOKEN,
        systemVariables: SYSTEM_VARIABLES,
        userVariables: USER_VARIABLES
      });
      // script 方式は右下にバブル表示（オーバーレイ）。必要なら自動オープン可能。
      // openBubbleIfAny(); // ←自動で開きたい時だけ有効化
    } else {
      // 既定は iframe 方式（公式 docs の埋め込み方法のひとつ）:contentReference[oaicite:14]{index=14}
      mountDifyIframe({ container: embedHost, appUrl: DIFY_APP_URL, height: '70vh' });
    }
  } else {
    // 自作UI（SSE or デモ応答）
    mountCustomChat({
      container: customHost,
      apiUrl: '/api/chat' // バックエンドのSSEプロキシがあればここに設定。なければ自動でデモ応答。
    });
  }

  // ヘッダー操作
  $('toggle-btn').addEventListener('click', () => {
    const current = USE_DIFY_EMBED;
    const nextUrl = new URL(location.href);
    nextUrl.searchParams.set('embed', String(!current));
    location.href = nextUrl.toString();
  });

  $('open-bubble').addEventListener('click', () => openBubbleIfAny());
});
