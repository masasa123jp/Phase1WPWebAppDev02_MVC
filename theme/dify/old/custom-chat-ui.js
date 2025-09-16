// custom-chat-ui.js

/**
 * mountCustomChat({ container, apiUrl })
 * - container: チャットを描画するDOM要素
 * - apiUrl: バックエンドの SSE プロキシ（未設定/到達不可ならデモ応答）
 */
export function mountCustomChat({ container, apiUrl = '/api/chat' } = {}) {
  if (!container) throw new Error('container が未指定です');

  // ------- UI（最低限のマークアップを注入） -------
  container.innerHTML = `
    <style>
      .cc-wrap{display:flex;flex-direction:column;height:70vh}
      .cc-msgs{flex:1;overflow:auto;padding:12px;background:#f9fafb;display:flex;flex-direction:column;gap:8px}
      .cc-msg{max-width:80%;padding:8px 12px;border-radius:14px;line-height:1.5;word-wrap:break-word}
      .cc-msg.user{align-self:flex-end;background:#e0f2fe;color:#0c4a6e}
      .cc-msg.bot{align-self:flex-start;background:#fff7ed;color:#92400e}
      .cc-form{display:flex;gap:8px;border-top:1px solid #e5e7eb;padding:8px;background:#fff}
      .cc-form textarea{flex:1;resize:none;border:1px solid #e5e7eb;border-radius:8px;padding:8px;min-height:40px;max-height:120px;font:inherit}
      .cc-form button{border:none;background:#1e40af;color:#fff;border-radius:8px;padding:8px 12px;cursor:pointer}
      .cc-form button[disabled]{background:#9ca3af;cursor:not-allowed}
      .cc-stop{background:#dc2626}
      .cc-title{padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#fff;font-weight:700}
    </style>
    <div class="cc-wrap">
      <div class="cc-title">新しいチャット</div>
      <div class="cc-msgs" id="cc-msgs"></div>
      <form class="cc-form" id="cc-form">
        <textarea id="cc-input" placeholder="メッセージを入力..." rows="1"></textarea>
        <button id="cc-send" type="submit" title="送信">送信</button>
        <button id="cc-stop" type="button" class="cc-stop" hidden>停止</button>
      </form>
    </div>
  `;

  const $ = (sel) => container.querySelector(sel);
  const msgs = $('#cc-msgs');
  const form = $('#cc-form');
  const input = $('#cc-input');
  const btnSend = $('#cc-send');
  const btnStop = $('#cc-stop');

  // 1会話をメモリで管理（必要なら localStorage 持続化にも拡張可）
  const conv = { id: randomId(), providerConvId: null, providerMsgId: null, messages: [] };
  let aborter = null;

  function randomId(){ return Math.random().toString(36).slice(2, 10); }
  function scrollToBottom(){ msgs.scrollTop = msgs.scrollHeight; }
  function append(role, content){
    const el = document.createElement('div');
    el.className = `cc-msg ${role}`;
    el.textContent = content;
    msgs.appendChild(el);
    scrollToBottom();
    return el;
  }
  function setAssistant(content){
    let last = msgs.lastElementChild;
    if (!last || !last.classList.contains('bot')) last = append('bot', '');
    last.textContent = content;
    scrollToBottom();
  }

  async function send(text){
    conv.messages.push({ role: 'user', content: text });
    append('user', text);
    input.value = '';
    input.style.height = 'auto';
    btnSend.disabled = true; btnStop.hidden = false;

    let collected = '';
    aborter = new AbortController();

    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          query: text,
          conversationId: conv.providerConvId || undefined,
          userId: conv.id
        }),
        signal: aborter.signal
      });

      // API に到達できない → デモ応答へ
      if (!res.ok || !res.body) throw new Error('API not reachable');

      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buf = '';
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buf += decoder.decode(value, { stream: true });
        const lines = buf.split('\n'); buf = lines.pop();
        for (const line of lines) {
          if (!line.startsWith('data:')) continue;
          const json = line.replace(/^data:\s*/, '');
          if (json === '[DONE]') continue;
          try {
            const payload = JSON.parse(json);
            if (payload.answer) { collected += payload.answer; setAssistant(collected); }
            if (payload.conversation_id) conv.providerConvId = payload.conversation_id;
            if (payload.message_id)      conv.providerMsgId  = payload.message_id;
          } catch {/* パースできない行は無視 */}
        }
      }
    } catch (e) {
      // ---- デモ応答（SSEなしでもUI確認できるよう簡易生成） ----
      const demo = 'これはデモ応答です。バックエンドの /api/chat を用意すると、ストリーミングで応答します。';
      for (const ch of demo) {
        if (aborter?.signal.aborted) break;
        collected += ch; setAssistant(collected);
        await new Promise(r => setTimeout(r, 30));
      }
    } finally {
      btnSend.disabled = false; btnStop.hidden = true;
    }
  }

  // 入力欄の自動リサイズ
  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });

  // 送信
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (text) send(text);
  });

  // 停止（SSEの中断）
  btnStop.addEventListener('click', () => {
    if (aborter) aborter.abort();
    btnSend.disabled = false; btnStop.hidden = true;
  });
}
