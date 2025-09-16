// js/custom-chat-ui.js
// 目的：自作の Dify 風チャット UI を描画。
// - サーバに /api/chat があれば、Dify の /v1/chat-messages をプロキシして
//   response_mode: "streaming" で SSE を受信してストリーム描画。:contentReference[oaicite:14]{index=14}
// - API が無い/到達不可ならデモ応答にフォールバック。
// デザインは styles.css のトーン（色・角丸）に合わせて実装:contentReference[oaicite:15]{index=15}。

export function mountCustomChat({ container, apiUrl = '/api/chat' } = {}) {
  if (!container) throw new Error('container が未指定です');

  // --------- UI マークアップ（styles.css 準拠の色/角丸） ---------
  container.innerHTML = `
    <style>
      .cc-wrap{display:flex;flex-direction:column;height:100%}
      .cc-title{padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#fff;font-weight:700;color:var(--base-color)}
      .cc-msgs{flex:1;overflow:auto;padding:12px;background:#f9fafb;display:flex;flex-direction:column;gap:8px}
      .cc-msg{max-width:80%;padding:8px 12px;border-radius:14px;line-height:1.5;word-wrap:break-word}
      .cc-msg.user{align-self:flex-end;background:var(--main-color);color:var(--base-color)}
      .cc-msg.bot{align-self:flex-start;background:var(--base-color);color:#fff}
      .cc-form{display:flex;gap:8px;border-top:1px solid #e5e7eb;padding:8px;background:#fff}
      .cc-form textarea{flex:1;resize:none;border:1px solid #ccc;border-radius:var(--border-radius);padding:8px;min-height:40px;max-height:120px;font:inherit}
      .cc-form button{border:1px solid transparent;background:var(--base-color);color:#fff;border-radius:var(--border-radius);padding:8px 12px;cursor:pointer}
      .cc-form button[disabled]{background:#9ca3af;cursor:not-allowed}
      .cc-stop{background:#dc2626}
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

      // API に到達できない場合はフォールバックへ
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
          } catch { /* パース不可の行は無視 */ }
        }
      }
    } catch {
      // ---- デモ応答（SSEなしでもUI確認できる） ----
      const demo = 'これはデモ応答です。/api/chat を用意するとストリーミングで表示されます。';
      for (const ch of demo) {
        if (aborter?.signal.aborted) break;
        collected += ch; setAssistant(collected);
        await new Promise(r => setTimeout(r, 25));
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
