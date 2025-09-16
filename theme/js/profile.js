/*
  profile.js – マイページの表示・編集処理

  ローカルストレージからユーザー情報を読み込み、プロフィールカードに表示します。
  また、お気に入りの数をカウントして表示します。フォーム送信時には入力内容
  をローカルストレージへ反映させます。
*/

// ===== Analytics Helpers (Profile) =====
function roroEnsureSession() {
  try {
    if (!localStorage.getItem('roro_sid')) {
      const uuid = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random());
      localStorage.setItem('roro_sid', uuid);
    }
  } catch (e) {}
}
function roroSendBeaconJSON(url, payload) {
  try {
    const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
    navigator.sendBeacon(url, blob);
  } catch (e) {
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).catch(() => {});
  }
}
function trackProfileView() {
  roroEnsureSession();
  try {
    roroSendBeaconJSON('/wp-json/roro-analytics/v1/view', {
      issue_id: 'profile',
      page_id: null,
      dwell_ms: null,
      session_id: localStorage.getItem('roro_sid'),
      referer: document.referrer || null,
      device: /Mobi/i.test(navigator.userAgent) ? 'mobile' : 'pc',
      lang: (navigator.language || 'ja')
    });
  } catch (e) {}
}

document.addEventListener('DOMContentLoaded', () => {
  // Track view event for the profile page
  try { trackProfileView(); } catch (err) {}
  // ログインしていない場合はリダイレクト
  requireLogin();
  // ユーザーデータの取得（名前やふりがな等は登録時にセッションストレージへ保存されている想定）
  const userData = JSON.parse(sessionStorage.getItem('user')) || {};
  // プロフィールカードへの表示要素
  const nameEl = document.getElementById('profile-name');
  const locationEl = document.getElementById('profile-location');
  const favCountEl = document.getElementById('fav-count');
  // フォームの入力要素
  const nameInput = document.getElementById('profile-name-input');
  const furiganaInput = document.getElementById('profile-furigana-input');
  const emailInput = document.getElementById('profile-email');
  const phoneInput = document.getElementById('profile-phone');
  // 新しい住所入力フィールド
  const prefectureInput = document.getElementById('profile-prefecture');
  const cityInput = document.getElementById('profile-city');
  const address1Input = document.getElementById('profile-address1');
  const buildingInput = document.getElementById('profile-building');
  // ペット情報UI
  const petsContainer = document.getElementById('pets-container');
  const addPetBtn = document.getElementById('add-pet-btn');
  const languageSelect = document.getElementById('profile-language');
  // 郵便番号フィールド
  const zipInput = document.getElementById('profile-zip');

  /**
   * Apply the address data returned from the postcode lookup.  Depending on
   * the current language (getUserLang() or select value), this function
   * chooses either the Japanese or romanised form.  Prefecture is
   * applied using the Japanese value since our select options are
   * Japanese.  City and address1 are filled with the appropriate
   * representation.
   *
   * @param {Object} payload Address data returned by roro-geo/v1/postcode
   */
  function applyZipData(payload) {
    if (!payload) return;
    // Determine current language: if languageSelect is present, use its value; else fallback to getUserLang()
    let lang = 'ja';
    if (languageSelect && languageSelect.value) {
      lang = languageSelect.value;
    } else if (typeof getUserLang === 'function') {
      try { lang = getUserLang(); } catch (e) {}
    }
    const useRoma = (lang && lang !== 'ja');
    // Prefecture: always use Japanese for select. If not available, fallback to romanisation.
    const pref = payload.pref || payload.pref_roma || '';
    if (pref) {
      for (const opt of prefectureInput.options) {
        if (opt.value === pref) {
          prefectureInput.value = opt.value;
          break;
        }
      }
    }
    // City and town fields: choose based on language
    const cityVal = useRoma ? (payload.city_roma || payload.city || '') : (payload.city || payload.city_roma || '');
    const townVal = useRoma ? (payload.town_roma || payload.town || '') : (payload.town || payload.town_roma || '');
    cityInput.value = cityVal;
    address1Input.value = townVal;
  }

  /**
   * Fetch address information from the server for the given postal code.  If
   * the postal code is too short, nothing will happen.  Debounce calls
   * externally.
   *
   * @param {string} zip Raw zip string
   */
  async function fetchZip(zip) {
    const digits = (zip || '').replace(/[^0-9]/g, '');
    if (digits.length < 3) return;
    try {
      // Add a CSS class for a loading indicator if desired
      zipInput.classList.add('loading');
      const res = await fetch(`/wp-json/roro-geo/v1/postcode?zip=${encodeURIComponent(digits)}`, { credentials: 'same-origin' });
      if (res.ok) {
        const data = await res.json();
        applyZipData(data);
      }
    } catch (err) {
      console.warn('Postal code lookup failed', err);
    } finally {
      zipInput.classList.remove('loading');
    }
  }

  // Attach listeners to the zip input for auto lookup
  if (zipInput) {
    let zipTimer;
    const triggerLookup = () => {
      clearTimeout(zipTimer);
      zipTimer = setTimeout(() => {
        fetchZip(zipInput.value);
      }, 400);
    };
    zipInput.addEventListener('input', triggerLookup);
    zipInput.addEventListener('blur', () => {
      fetchZip(zipInput.value);
    });
  }
  // お気に入り数の読み込み
  let favorites;
  try {
    favorites = JSON.parse(localStorage.getItem('favorites')) || [];
  } catch (e) {
    favorites = [];
  }
  favCountEl.textContent = favorites.length;
  // フォロワー・フォロー数（現状は0固定）
  document.getElementById('followers').textContent = 0;
  document.getElementById('following').textContent = 0;
  // 名前・ふりがなは表示のみ
  nameEl.textContent = userData.name || 'ゲストユーザー';
  // フォームに初期値を設定（名前とふりがなは編集不可）
  nameInput.value = userData.name || '';
  furiganaInput.value = userData.furigana || '';
  emailInput.value = userData.email || '';
  phoneInput.value = userData.phone || '';
  // 言語セレクトの初期値
  if (languageSelect) {
    const lang = userData.language || (typeof getUserLang === 'function' ? getUserLang() : 'ja');
    languageSelect.value = lang;
  }
  // 現在のペット情報（サーバから取得後に上書き）
  let pets = [];
  // 初期のペットIDリストを保持し、更新時に差分判定するためのコピー
  let initialPets = [];

  /**
   * ペット情報のフォームを描画します。pets 配列の内容を基に、入力欄を生成します。
   */
  function renderPets() {
    // いったんクリア
    petsContainer.innerHTML = '';
    // 現在の言語を取得
    let currentLang = 'ja';
    try {
      if (typeof getUserLang === 'function') {
        currentLang = getUserLang();
      }
    } catch (e) {}
    // 翻訳辞書からプレースホルダーを取得
    let breedPlaceholder = '犬種を選択';
    try {
      if (window.translations && window.translations[currentLang] && window.translations[currentLang]['select_breed']) {
        breedPlaceholder = window.translations[currentLang]['select_breed'];
      }
    } catch (e) {}
    pets.forEach((pet, index) => {
      const petDiv = document.createElement('div');
      petDiv.className = 'pet-item';
      petDiv.style.marginBottom = '0.5rem';
      const wrapper = document.createElement('div');
      wrapper.style.display = 'flex';
      wrapper.style.gap = '0.5rem';
      wrapper.style.flexWrap = 'wrap';
      wrapper.style.alignItems = 'flex-end';
      // 種類セレクト
      const typeSelect = document.createElement('select');
      typeSelect.innerHTML = `
        <option value="dog">犬</option>
        <option value="cat">猫</option>
        <option value="other">その他</option>
      `;
      typeSelect.value = pet.type || 'dog';
      typeSelect.style.flex = '1 1 20%';
      // 犬種セレクト（犬の場合のみ有効）
      const breedSelect = document.createElement('select');
      const dogBreeds = [
        '',
        'トイ・プードル',
        'チワワ',
        '混血犬（体重10kg未満）',
        '柴',
        'ミニチュア・ダックスフンド',
        'ポメラニアン',
        'ミニチュア・シュナウザー',
        'ヨークシャー・テリア',
        'フレンチ・ブルドッグ',
        'マルチーズ',
        'シー・ズー',
        'カニーンヘン・ダックスフンド',
        'パピヨン',
        'ゴールデン・レトリーバー',
        'ウェルシュ・コーギー・ペンブローク',
        'ジャック・ラッセル・テリア',
        'ラブラドール・レトリーバー',
        'パグ',
        'キャバリア・キング・チャールズ・スパニエル',
        'ミニチュア・ピンシャー',
        '混血犬（体重10kg以上20kg未満）',
        'ペキニーズ',
        'イタリアン・グレーハウンド',
        'ボーダー・コーリー',
        'ビーグル',
        'ビション・フリーゼ',
        'シェットランド・シープドッグ',
        'ボストン・テリア',
        'アメリカン・コッカー・スパニエル',
        '日本スピッツ'
      ];
      dogBreeds.forEach((breed) => {
        const opt = document.createElement('option');
        opt.value = breed;
        // 犬種名を現在の言語で翻訳
        let label;
        if (!breed) {
          label = breedPlaceholder;
        } else {
          try {
            if (typeof translateBreed === 'function') {
              label = translateBreed(breed, currentLang);
            } else {
              label = breed;
            }
          } catch (err) {
            label = breed;
          }
        }
        opt.textContent = label;
        breedSelect.appendChild(opt);
      });
      breedSelect.value = pet.breed || '';
      breedSelect.style.flex = '1 1 20%';
      breedSelect.disabled = (pet.type !== 'dog');
      // 名前入力
      const nameInputEl = document.createElement('input');
      nameInputEl.type = 'text';
      nameInputEl.value = pet.name || '';
      nameInputEl.placeholder = '名前';
      nameInputEl.style.flex = '1 1 20%';
      // 年齢セレクト
      const ageSelect = document.createElement('select');
      ageSelect.innerHTML = `
        <option value="puppy">子犬/子猫 (1歳未満)</option>
        <option value="adult">成犬/成猫 (1〜7歳)</option>
        <option value="senior">シニア犬/シニア猫 (7歳以上)</option>
      `;
      ageSelect.value = pet.age || 'puppy';
      ageSelect.style.flex = '1 1 20%';
      // 削除ボタン
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = '削除';
      removeBtn.className = 'btn danger-btn';
      removeBtn.addEventListener('click', () => {
        pets.splice(index, 1);
        renderPets();
      });
      // 種類変更に応じて犬種セレクトの有効/無効を切り替え
      typeSelect.addEventListener('change', () => {
        if (typeSelect.value === 'dog') {
          breedSelect.disabled = false;
        } else {
          breedSelect.disabled = true;
          breedSelect.value = '';
        }
      });
      // 値を格納するためのデータ監視
      typeSelect.addEventListener('change', () => {
        pet.type = typeSelect.value;
      });
      breedSelect.addEventListener('change', () => {
        pet.breed = breedSelect.value;
      });
      nameInputEl.addEventListener('input', () => {
        pet.name = nameInputEl.value;
      });
      ageSelect.addEventListener('change', () => {
        pet.age = ageSelect.value;
      });
      wrapper.appendChild(typeSelect);
      wrapper.appendChild(breedSelect);
      wrapper.appendChild(nameInputEl);
      wrapper.appendChild(ageSelect);
      wrapper.appendChild(removeBtn);
      petDiv.appendChild(wrapper);
      petsContainer.appendChild(petDiv);
    });
  }

  /**
   * サーバからプロフィールとペット情報を取得し、フォームに反映します。
   */
  async function loadRemoteProfile() {
    try {
      const profileRes = await fetch('/wp-json/roro/me/customer');
      if (profileRes.ok) {
        const profileData = await profileRes.json();
        // 住所情報を入力
        if (profileData.prefecture !== undefined) {
          prefectureInput.value = profileData.prefecture || '';
        }
        if (profileData.city !== undefined) {
          cityInput.value = profileData.city || '';
        }
        if (profileData.address_line1 !== undefined) {
          address1Input.value = profileData.address_line1 || '';
        }
        if (profileData.building !== undefined) {
          buildingInput.value = profileData.building || '';
        }
        if (profileData.phone !== undefined) {
          phoneInput.value = profileData.phone || '';
        }
        if (profileData.email !== undefined) {
          emailInput.value = profileData.email || '';
        }
        // マイページカードの場所を更新（都道府県＋市区町村＋番地）
        const locationStr = [profileData.prefecture, profileData.city, profileData.address_line1].filter(Boolean).join('');
        if (locationStr) {
          locationEl.textContent = locationStr;
        } else {
          locationEl.textContent = '';
        }
      }
      const petsRes = await fetch('/wp-json/roro/me/pets');
      if (petsRes.ok) {
        const petsArr = await petsRes.json();
        pets = petsArr.map((p) => {
          let type = 'other';
          if (p.species) {
            const sp = String(p.species).toLowerCase();
            if (sp === 'dog') type = 'dog';
            else if (sp === 'cat') type = 'cat';
            else type = 'other';
          }
          return {
            id: p.pet_id,
            type: type,
            breed: '',
            name: p.pet_name || '',
            age: 'adult'
          };
        });
        // 初期状態を保存（深いコピー）
        initialPets = pets.map(p => Object.assign({}, p));
        renderPets();
      }
    } catch (e) {
      console.error('Error loading profile', e);
    }
  }

  /**
   * 現在の言語に合わせて犬種セレクトの表示を更新します。
   *
   * applyTranslations() から呼び出され、言語変更時に自動で
   * renderPets() が再実行されます。
   */
  function updateBreedOptions() {
    try {
      renderPets();
    } catch (e) {
      /* ignore */
    }
  }
  // グローバルに公開
  window.updateBreedOptions = updateBreedOptions;
  // ロード時にサーバから情報を取得
  loadRemoteProfile();
  // ペット追加ボタン
  if (addPetBtn) {
    addPetBtn.addEventListener('click', () => {
      pets.push({ type: 'dog', breed: '', name: '', age: 'puppy' });
      renderPets();
    });
  }
  // プロフィールとペット情報の保存処理
  const form = document.getElementById('profile-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    // 入力値の取得
    const emailVal = emailInput.value.trim();
    const phoneVal = phoneInput.value.trim();
    const prefectureVal = prefectureInput.value.trim();
    const cityVal = cityInput.value.trim();
    const address1Val = address1Input.value.trim();
    const buildingVal = buildingInput.value.trim();
    // 簡易バリデーション
    if (emailVal && !/^\S+@\S+\.\S+$/.test(emailVal)) {
      alert('メールアドレスの形式が正しくありません');
      return;
    }
    if (phoneVal && !/^\+?\d[\d\- ]{6,}$/.test(phoneVal)) {
      alert('電話番号の形式が正しくありません');
      return;
    }
    // プロフィール情報のリモート更新
    const payload = {
      email: emailVal,
      phone: phoneVal,
      prefecture: prefectureVal,
      city: cityVal,
      address_line1: address1Val,
      building: buildingVal
    };
    try {
      await fetch('/wp-json/roro/me/customer', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
    } catch (err) {
      console.error('Failed to update profile', err);
    }
    // ペット情報の取得と差分判定
    const updatedPets = [];
    const wrappers = petsContainer.querySelectorAll('.pet-item');
    wrappers.forEach((div, idx) => {
      const selects = div.querySelectorAll('select');
      const inputs = div.querySelectorAll('input');
      const typeVal = selects[0].value;
      const breedVal = selects.length > 2 ? selects[1].value : '';
      const ageVal = selects.length > 2 ? selects[2].value : selects[1].value;
      const nameVal = inputs[0].value.trim();
      const existingPet = pets[idx] || {};
      updatedPets.push({ id: existingPet.id, type: typeVal, breed: breedVal, name: nameVal, age: ageVal });
    });
    const toDelete = initialPets.filter(p => p.id && !updatedPets.some(n => n.id === p.id));
    const toUpdate = updatedPets.filter(p => p.id);
    const toCreate = updatedPets.filter(p => !p.id);
    for (const p of toDelete) {
      try {
        await fetch(`/wp-json/roro/me/pets/${p.id}`, { method: 'DELETE' });
      } catch (err) {
        console.error('Failed to delete pet', err);
      }
    }
    for (const p of toUpdate) {
      const body = { species: (p.type || '').toUpperCase(), pet_name: p.name || '' };
      try {
        await fetch(`/wp-json/roro/me/pets/${p.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
      } catch (err) {
        console.error('Failed to update pet', err);
      }
    }
    for (const p of toCreate) {
      const body = { species: (p.type || '').toUpperCase(), pet_name: p.name || '' };
      try {
        await fetch('/wp-json/roro/me/pets', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
      } catch (err) {
        console.error('Failed to create pet', err);
      }
    }
    // ローカルの言語設定と連動
    if (languageSelect) {
      userData.language = languageSelect.value;
      if (typeof setUserLang === 'function') setUserLang(languageSelect.value);
    }
    userData.email = payload.email;
    userData.phone = payload.phone;
    sessionStorage.setItem('user', JSON.stringify(userData));
    // 更新後にページをリロードして反映
    location.reload();
  });
  // ログアウト処理
  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      sessionStorage.removeItem('user');
      if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.login) {
        location.href = RORO_ROUTES.login;
      } else {
        location.href = 'index.html';
      }
    });
  }
});