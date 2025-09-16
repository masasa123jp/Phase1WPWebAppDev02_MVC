// js/dify-switch.js
// 目的：ブール値（DEFAULT_USE_DIFY）や URL の ?embed=true|false で
//       「公式 Dify 画面」か「自作 Dify 風 UI」かを切り替える。

import { mountDifyIframe, mountDifyScript, openBubbleIfAny } from './dify-embed.js';
import { mountCustomChat } from './custom-chat-ui.js';

/** ====== 切替用の設定 ====== */
// 既定は true（＝公式画面を表示）。false で自作 UI。
const DEFAULT_USE_DIFY = true;

// 公式側の埋め込み方法： 'iframe' か 'script'
const EMBED_METHOD = 'iframe';

// ★ ここを指定のURLに更新
// iframe 方式の URL（既存 dify.html の実装を踏襲）
const DIFY_APP_URL = 'https://udify.app/chat/XDfUxgca6nAZWhdv';

// script 方式のベースURLとトークン（管理画面の Embedded → Script tag で取得）
const DIFY_BASE_URL = 'https://udify.app';
const EMBED_TOKEN   = ''; // ←発行トークンを入れると script 方式が有効化できます。

// （任意）ユーザーコンテキスト。script 方式で `window.difyChatbotConfig` に渡す。
const SYSTEM_VARIABLES = { user_id: '123' };
const USER_VARIABLES   = { user_name: 'ユーザー名' };

// 自作 UI が Dify と接続するための API プロキシ（未設置ならデモ応答にフォールバック）
const LOCAL_CHAT_API = '/api/chat'; // POST で {query, conversationId, userId} を受け取り SSE で返す想定。

/** ====== ユーティリティ ====== */
const $ = (id) => document.getElementById(id);

// URL の ?embed=true|false を解釈（上書き可能）
function getEmbedFlagFromQuery() {
  const v = new URL(location.href).searchParams.get('embed');
  if (v == null) return null;
  return /^(1|true|yes|on)$/i.test(v);
}

document.addEventListener('DOMContentLoaded', async () => {
  // 使う表示を決定
  const override = getEmbedFlagFromQuery();
  const USE_DIFY_EMBED = (override === null) ? DEFAULT_USE_DIFY : override;

  const embedArea  = $('embed-area');
  const embedHost  = $('embed-host');
  const customArea = $('custom-area');
  const customHost = $('custom-host');

  // 表示切替（styles.css に合わせてパネル表示）
  embedArea.style.display  = USE_DIFY_EMBED ? 'block' : 'none';
  customArea.style.display = USE_DIFY_EMBED ? 'none'  : 'block';

  // マウント
  if (USE_DIFY_EMBED) {
    if (EMBED_METHOD === 'script' && EMBED_TOKEN) {
      // script 方式：右下に“バブル”を生成（config → embed.min.js の順序は必須）
      await mountDifyScript({
        baseUrl: DIFY_BASE_URL,
        token: EMBED_TOKEN,
        systemVariables: SYSTEM_VARIABLES,
        userVariables: USER_VARIABLES,
      });
      // openBubbleIfAny(); // 自動で開きたい場合のみ
    } else {
      // 既定：iframe 方式（公式ドキュメントの基本埋め込み）
      mountDifyIframe({ container: embedHost, appUrl: DIFY_APP_URL, height: '100%' });
    }
  } else {
    // 自作 UI：SSE プロキシがあればストリーミング、なければデモ応答
    mountCustomChat({ container: customHost, apiUrl: LOCAL_CHAT_API });
  }

  // ヘッダー操作：ボタンクリックで true/false をトグル（URLクエリに反映）
  $('toggle-btn')?.addEventListener('click', () => {
    const nextUrl = new URL(location.href);
    nextUrl.searchParams.set('embed', String(!USE_DIFY_EMBED));
    location.href = nextUrl.toString();
  });

  // （script 方式用）バブルを開く補助
  $('open-bubble')?.addEventListener('click', () => openBubbleIfAny());
});
