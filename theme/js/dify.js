/*
  dify.js – AIチャットモック用スクリプト

  このスクリプトは Dify の AI チャットボットの代替として、
  ローカルで簡易的な会話応答を実装します。実際の生成 AI を
  呼び出すことなく、ユーザーからの質問に対してイベント情報を
  返したり、あいさつ応答を行ったりします。

  - ユーザーがメッセージを送信すると、チャット履歴にユーザー
    メッセージが追加され、タイピング風の遅延の後でボットの
    応答が表示されます。
  - 応答生成はシンプルなキーワード検出に基づき、現在読み込ま
    れている eventsData (data/events.js で定義) からイベント
    情報を抽出します。
*/

document.addEventListener('DOMContentLoaded', () => {
  // チャット要素の取得
  const chatContainer = document.getElementById('dify-chat');
  const messagesEl = chatContainer.querySelector('.chat-messages');
  const chatForm = document.getElementById('chat-form');
  const chatInput = document.getElementById('chat-input');
  // ログイン状態を確認
  requireLogin();
  // メッセージを DOM に追加する関数
  function appendMessage(text, sender) {
    const msgDiv = document.createElement('div');
    msgDiv.classList.add('message');
    msgDiv.classList.add(sender);
    // 改行を <br> に変換して表示
    msgDiv.innerHTML = text.replace(/\n/g, '<br>');
    messagesEl.appendChild(msgDiv);
    // スクロールを下まで
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }
  /**
   * ユーザーの入力に対する疑似的な応答を生成する。
   * @param {string} input ユーザー入力
   * @returns {string} 応答テキスト (HTML可)
   */
  function generateResponse(input) {
    const text = input.trim();
    if (!text) return '';
    const lower = text.toLowerCase();
    // あいさつパターン
    if (/^(こんにちは|こんばんは|おはよう|hi|hello)/i.test(text)) {
      return 'こんにちは！ご質問ありがとうございます。行きたい地域や気になるジャンルを教えてください。';
    }
    // お礼
    if (/ありがとう|thanks|thank you/i.test(text)) {
      return 'どういたしまして！他に知りたいことがあれば気軽に聞いてくださいね。';
    }
    // カフェ関連の質問に対応
    // 日本語・英語の両方に対応するため、小文字化した文字列を使用します
    if (/カフェ|ランチ|喫茶|コーヒー/.test(text) || /cafe|coffee|lunch|restaurant/.test(lower)) {
      // 簡易的なおすすめカフェリスト
      const cafes = [
        { name: '池袋 Rainy Days', area: '池袋', desc: '雨の日でも楽しめる室内ドッグカフェ' },
        { name: '代官山 Cafe Lily', area: '代官山', desc: '緑豊かなテラスが人気のカフェ' },
        { name: '吉祥寺 Paws Up', area: '吉祥寺', desc: 'ペット用メニューが充実したカフェ' }
      ];
      // 英語キーワードが含まれる場合は英語で応答
      const isEnglish = /cafe|coffee|lunch|restaurant/.test(lower);
      if (isEnglish) {
        let resp = 'Here are some recommended dog-friendly cafes:';
        resp += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
        cafes.forEach(c => {
          // 英語訳の地区名は日本語をそのまま用いる
          resp += `<li>${c.name} (${c.area}) – ${c.desc}</li>`;
        });
        resp += '</ul>';
        resp += 'For more details, please check the map page.';
        return resp;
      } else {
        let resp = 'おすすめのペット同伴カフェはこちらです：';
        resp += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
        cafes.forEach(c => {
          resp += `<li>${c.name}（${c.area}） – ${c.desc}</li>`;
        });
        resp += '</ul>';
        resp += '詳しい情報はマップページでご確認ください。';
        return resp;
      }
    }
    // 商品・グッズの質問に対応 (日本語・英語)
    if (/商品|グッズ|アイテム|おすすめ.*(服|マット|グッズ)/.test(text) || /product|goods|item|items|merch/.test(lower)) {
      const products = [
        { name: 'UVカット冷感犬服', price: '¥2,980', desc: '暑い日でもひんやり快適に過ごせるウェア' },
        { name: 'ひんやりアルミマット', price: '¥1,980', desc: '自宅でも外出先でも使える冷却マット' },
        { name: '防水レインコート', price: '¥3,500', desc: '梅雨のお散歩に必須の防水コート' }
      ];
      const isEnglish = /product|goods|item|items|merch/.test(lower);
      if (isEnglish) {
        let resp = 'Here are some recommended pet products:';
        resp += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
        products.forEach(p => {
          resp += `<li>${p.name} (${p.price}) – ${p.desc}</li>`;
        });
        resp += '</ul>';
        resp += 'You can also check our magazine page for details.';
        return resp;
      } else {
        let resp = 'おすすめペットグッズをご紹介します：';
        resp += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
        products.forEach(p => {
          resp += `<li>${p.name}（${p.price}） – ${p.desc}</li>`;
        });
        resp += '</ul>';
        resp += '詳細は雑誌ページでもご覧いただけます。';
        return resp;
      }

    // 占いのリクエストに対応
    if (/占い|今日の運勢|horoscope/i.test(text)) {
      // 簡易的な星座別メッセージを用意します
      const fortunes = [
        '牡羊座: 新しい冒険があなたを待っています！',
        '牡牛座: 家族との時間を大切にしましょう。',
        '双子座: 新しい出会いがあるかも？',
        '蟹座: 自分の直感に正直に従いましょう。',
        '獅子座: 周りからの注目を浴びる予感！',
        '乙女座: 健康に気をつけて、休息を心がけて。',
        '天秤座: バランスを大切に、自分のペースで。',
        '蠍座: 情熱を持って取り組めば良い結果が得られます。',
        '射手座: 新しい知識を吸収するチャンス！',
        '山羊座: 計画性が成功の鍵になります。',
        '水瓶座: 友人との交流が運気アップに。',
        '魚座: 自分の感性を信じて行動しましょう。'
      ];
      let resp = '<strong>ちぃまめの今日の占い</strong><br>';
      resp += fortunes.map(f => `• ${f}`).join('<br>');
      return resp;
    }

    // ワンポイントアドバイスへの質問に対応
    if (/アドバイス|ワンポイント|advice|tip|tips/i.test(text)) {
      const advices = [
        '散歩後は足をきれいに拭いてあげましょう。',
        '暑い日は早朝や夕方に散歩を。',
        '室内でもおもちゃで遊んで運動不足を解消しましょう。',
        '定期的な健康チェックで病気を予防しましょう。'
      ];
      let resp = 'ちぃまめからのワンポイントアドバイス：';
      resp += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
      advices.forEach(a => {
        resp += `<li>${a}</li>`;
      });
      resp += '</ul>';
      return resp;
    }
    }
    // 「イベント」や「おすすめ」が含まれている場合、キーワード検索
    const keywords = ['イベント', 'おすすめ', 'イベント情報', '探す', 'スポット', 'event', 'events', 'recommend', 'spot'];
    const containsQuery = keywords.some((kw) => text.toLowerCase().includes(kw));
    // 場所キーワード
    const prefectures = ['北海道','青森','岩手','宮城','秋田','山形','福島','茨城','栃木','群馬','埼玉','千葉','東京','東京都','神奈川','新潟','富山','石川','福井','山梨','長野','岐阜','静岡','愛知','三重','滋賀','京都','大阪','兵庫','奈良','和歌山','鳥取','島根','岡山','広島','山口','徳島','香川','愛媛','高知','福岡','佐賀','長崎','熊本','大分','宮崎','鹿児島','沖縄'];
    let locationQuery = null;
    prefectures.forEach((pref) => {
      if (text.includes(pref)) {
        locationQuery = pref;
      }
    });
    // もしイベントやおすすめを尋ねている場合
    if (containsQuery || locationQuery) {
      // イベントデータが利用可能な場合にのみ検索
      const allEvents = Array.isArray(window.eventsData) ? window.eventsData : [];
      let filtered = allEvents;
      // 日付の昇順で並べ替え (ISO日付文字列)
      filtered = filtered.slice().sort((a, b) => {
        const da = a.date && a.date !== 'nan' ? a.date : '9999-12-31';
        const db = b.date && b.date !== 'nan' ? b.date : '9999-12-31';
        return da.localeCompare(db);
      });
      // 場所フィルタ
      if (locationQuery) {
        filtered = filtered.filter((ev) => {
          // prefecture または address に含まれるかで判定
          return (ev.prefecture && ev.prefecture.includes(locationQuery)) || (ev.address && ev.address.includes(locationQuery));
        });
      }
      if (filtered.length === 0) {
        // イベントが見つからない場合は日本語または英語で応答
        if (/event|events|recommend|spot/.test(lower)) {
          return 'Sorry, no events were found for that area. Please try another location or date.';
        } else {
          return '申し訳ありません。その地域のイベントは見つかりませんでした。他の地域や日付でお試しください。';
        }
      }
      // 上位3件を表示
      const top = filtered.slice(0, 3);
      const isEnglishEvent = /event|events|recommend|spot/.test(lower);
      let response = '';
      if (isEnglishEvent) {
        if (locationQuery) {
          response += `Here are some recommended events around ${locationQuery}:`;
        } else {
          response += 'Here are some recommended events:';
        }
        response += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
        top.forEach((ev) => {
          const date = (ev.date && ev.date !== 'nan') ? ev.date : 'TBD';
          const pref = (ev.prefecture && ev.prefecture !== 'nan') ? ev.prefecture : '';
          response += `<li>${ev.name} (${date} @ ${pref})</li>`;
        });
        response += '</ul>';
        response += 'For more details, click the markers on the map page.';
        return response;
      } else {
        if (locationQuery) {
          response += `${locationQuery}周辺のおすすめイベントはこちらです：`;
        } else {
          response += 'おすすめのイベントはこちらです：';
        }
        response += '<ul style="padding-left:1rem;margin-top:0.5rem;">';
        top.forEach((ev) => {
          const date = (ev.date && ev.date !== 'nan') ? ev.date : '日程未定';
          const pref = (ev.prefecture && ev.prefecture !== 'nan') ? ev.prefecture : '';
          response += `<li>${ev.name}（${date} @ ${pref}）</li>`;
        });
        response += '</ul>';
        response += '詳しい情報はマップページのマーカーをクリックしてください。';
        return response;
      }
    }
    // その他の質問には汎用応答とヒント
    return 'うまく理解できませんでした。ペットイベントやおすすめスポット、カフェ、グッズについて知りたい場合はそのキーワードや地域名を含めて質問してみてください。';
  }
  // フォーム送信イベントハンドラ
  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const userText = chatInput.value.trim();
    if (!userText) return;
    // ユーザーメッセージを表示
    appendMessage(userText, 'user');
    chatInput.value = '';
    // ボットの応答を遅延表示 (タイピング風)
    // 一旦 "..." と表示して疑似的な応答待ちにする
    const typingIndicator = document.createElement('div');
    typingIndicator.classList.add('message', 'bot');
    typingIndicator.textContent = '...';
    messagesEl.appendChild(typingIndicator);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    setTimeout(() => {
      messagesEl.removeChild(typingIndicator);
      const reply = generateResponse(userText);
      appendMessage(reply, 'bot');
    }, 600);
  });
});