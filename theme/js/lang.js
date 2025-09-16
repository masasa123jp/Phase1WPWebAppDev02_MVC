/*
  lang.js – 多言語対応スクリプト

  サイト全体で使用するテキストの翻訳辞書を定義し、ユーザーの選択に応じて
  表示言語を切り替える仕組みを提供します。ページ読み込み時に現在の
  言語設定を読み込み、data-i18n-key 属性が指定された要素に翻訳を適用します。
  また、言語切替ボタン（globe アイコン）を使って順番に言語を変更できます。
*/

// 翻訳辞書: 各キーに対して4言語分の翻訳を提供します
const translations = {
  ja: {
    nav_map: 'マップ',
    nav_ai: 'AI',
    nav_favorites: 'お気に入り',
    nav_magazine: '雑誌',
    nav_profile: 'マイページ',
    profile_title: 'マイページ',
    magazine_title: '月間雑誌',
    map_title: 'おでかけマップ',
    ai_title: 'AIアシスタント',
    favorites_title: 'お気に入り',
    profile_edit: 'プロフィール編集',
    label_name: 'お名前',
    label_furigana: 'ふりがな',
    label_email: 'メールアドレス',
    label_phone: '電話番号',
    label_address: '住所',
    label_language: '言語',
    pet_info: 'ペット情報',
    add_pet: 'ペットを追加',
    save: '保存',
    logout: 'ログアウト',
    no_favorites: 'お気に入りがまだありません。',
    delete: '削除',
    // dify.html で外部AIチャットウィンドウを埋め込むようにしたため説明文を更新
    ai_intro: 'AIアシスタントにペットの気になることを気軽に質問してみましょう。',
    ai_note: '※このチャットはモックアップです。実際のAI連携にはDifyが提供するスクリプトを読み込んでください。',
    send: '送信',
    chat_placeholder: 'メッセージを入力...',
    reset_view: '周辺表示'
    ,
    /* 共有・推薦 */
    share: '共有',
    share_x: 'Xで共有',
    share_instagram: 'Instagramで共有',
    // LINE共有ボタンのラベル
    share_line: 'LINEで共有',
    copy_message: 'リンクをコピーしました。Instagramで貼り付けて共有してください。',
    recommend_title: 'おすすめイベント',
    /* カテゴリ名称 */
    cat_event: 'イベント',
    cat_restaurant: 'レストラン',
    cat_hotel: 'ホテル',
    cat_activity: 'アクティビティ',
    cat_museum: '美術館・博物館',
    cat_facility: '施設',
    cat_spot: 'スポット',
    /* 保存メニューとリスト種別 */
    save_favorite: 'お気に入り',
    save_want: '行ってみたい',
    save_plan: '旅行プラン',
    save_star: 'スター付き',
    list_favorite: 'お気に入り',
    list_want: '行ってみたい',
    list_plan: '旅行プラン',
    list_star: 'スター付き',
    // ペットプロフィール用: 犬種セレクトのプレースホルダー
    select_breed: '犬種を選択',
    /* インフォウィンドウ関連 */
    view_details: '詳細を見る',
    saved_msg: 'リストに保存しました',
    already_saved_msg: '既にこのリストに登録済みです',
    /* Magazine specific translations */
    mag_issue_june: '2025年6月号',
    mag_theme_june: '犬と梅雨のおうち時間',
    mag_desc_june: '雨の日でも犬と楽しく過ごせる特集',
    mag_issue_july: '2025年7月号',
    mag_theme_july: '犬と夏のおでかけ × UVケア',
    mag_desc_july: '紫外線対策とワンちゃんとのおでかけスポットをご紹介♪',
    mag_event_title: '今月のイベント',
    mag_cafe_title: 'おすすめカフェ',
    mag_column_june: 'プチコラム：梅雨の運動不足解消法',
    mag_column_july: 'プチコラム：夏のUV対策',
    mag_recommend_title: 'ちぃまめのおすすめ',
    mag_disaster_title: 'ちぃまめの防災アドバイス',
    mag_advice_title: 'ワンポイントアドバイス',
    mag_relax_cafe_title: 'ワンちゃんとくつろげるカフェ'
    ,login_greeting: 'こんにちは！'
    ,login_email: 'メールアドレス'
    ,login_password: 'パスワード'
    ,login_submit: 'ログイン'
    ,login_google: 'Googleでログイン'
    ,login_line: 'LINEでログイン'
    ,login_no_account: 'アカウントをお持ちでない場合は'
    ,login_register_link: 'こちらから新規登録'
  },
  en: {
    nav_map: 'Map',
    nav_ai: 'AI',
    nav_favorites: 'Favorites',
    nav_magazine: 'Magazine',
    nav_profile: 'Profile',
    profile_title: 'My Page',
    magazine_title: 'Monthly Magazine',
    map_title: 'Outing Map',
    ai_title: 'AI Assistant',
    favorites_title: 'Favorites',
    profile_edit: 'Edit Profile',
    label_name: 'Name',
    label_furigana: 'Furigana',
    label_email: 'Email Address',
    label_phone: 'Phone Number',
    label_address: 'Address',
    label_language: 'Language',
    pet_info: 'Pet Info',
    add_pet: 'Add Pet',
    save: 'Save',
    logout: 'Logout',
    no_favorites: 'No favorites yet.',
    delete: 'Remove',
    // updated description for external AI assistant window
    ai_intro: 'You can chat with our external AI assistant in the window below. Feel free to ask the AI assistant any questions you have about your pet.',
    ai_note: '*This chat is a mockup. Please load the official Dify script for real AI integration.',
    send: 'Send',
    chat_placeholder: 'Enter your message...',
    reset_view: 'Reset View'
    ,
    /* Sharing & Recommendations */
    share: 'Share',
    share_x: 'Share on X',
    share_instagram: 'Share on Instagram',
    // LINE share button label
    share_line: 'Share on LINE',
    copy_message: 'Link copied. Please paste in Instagram to share.',
    recommend_title: 'Recommended Events',
    /* Category names */
    cat_event: 'Events',
    cat_restaurant: 'Restaurants',
    cat_hotel: 'Hotels',
    cat_activity: 'Activities',
    cat_museum: 'Museums',
    cat_facility: 'Facilities',
    cat_spot: 'Spots',
    /* Save menu and list labels */
    save_favorite: 'Favorite',
    save_want: 'Want to Visit',
    save_plan: 'Travel Plan',
    save_star: 'Starred',
    list_favorite: 'Favorite',
    list_want: 'Want to Visit',
    list_plan: 'Travel Plan',
    list_star: 'Starred',
    // Pet profile: placeholder for breed select
    select_breed: 'Select breed',
    /* InfoWindow */
    view_details: 'View details',
    saved_msg: 'Added to list',
    already_saved_msg: 'Already registered in this list',
    /* Magazine translations */
    mag_issue_june: 'June 2025 Issue',
    mag_theme_june: 'Indoor Fun with Dogs in the Rainy Season',
    mag_desc_june: 'A special feature on enjoying rainy days with your dog',
    mag_issue_july: 'July 2025 Issue',
    mag_theme_july: 'Summer Outings × UV Care',
    mag_desc_july: 'Introducing UV protection and dog-friendly outing spots',
    mag_event_title: 'Events This Month',
    mag_cafe_title: 'Recommended Café',
    mag_column_june: 'Mini Column: Exercise in the Rainy Season',
    mag_column_july: 'Mini Column: Summer UV Protection',
    mag_recommend_title: "Chiamame's Recommendations",
    mag_disaster_title: "Chiamame's Disaster Advice",
    mag_advice_title: 'One-Point Advice',
    mag_relax_cafe_title: 'Cafés to Relax with Your Dog'
    ,login_greeting: 'Hello!'
    ,login_email: 'Email Address'
    ,login_password: 'Password'
    ,login_submit: 'Login'
    ,login_google: 'Login with Google'
    ,login_line: 'Login with LINE'
    ,login_no_account: 'If you do not have an account'
    ,login_register_link: 'Register here'
  },
  zh: {
    nav_map: '地图',
    nav_ai: 'AI',
    nav_favorites: '收藏',
    nav_magazine: '杂志',
    nav_profile: '我的主页',
    profile_title: '我的主页',
    magazine_title: '月刊杂志',
    map_title: '外出地图',
    ai_title: 'AI助手',
    favorites_title: '收藏',
    profile_edit: '编辑个人信息',
    label_name: '姓名',
    label_furigana: '读音',
    label_email: '电子邮件',
    label_phone: '电话号码',
    label_address: '地址',
    label_language: '语言',
    pet_info: '宠物信息',
    add_pet: '添加宠物',
    save: '保存',
    logout: '退出登录',
    no_favorites: '暂无收藏。',
    delete: '删除',
    // 更新説明：外部AIチャットウィンドウに対応
    ai_intro: '您可以在下方窗口中与我们的外部 AI 助手聊天。您可以随时向 AI 助手询问任何有关宠物的问题。',
    ai_note: '*该聊天为模型展示。要启用真正的AI功能，请加载Dify官方脚本。',
    send: '发送',
    chat_placeholder: '输入您的信息...',
    reset_view: '重置视图'
    ,
    /* 共享与推荐 */
    share: '分享',
    share_x: '在X上分享',
    share_instagram: '在Instagram上分享',
    // LINE 分享按钮标签
    share_line: '用LINE分享',
    copy_message: '链接已复制。请在Instagram上粘贴以分享。',
    recommend_title: '推荐活动',
    /* 分类名称 */
    cat_event: '活动',
    cat_restaurant: '餐厅',
    cat_hotel: '酒店',
    cat_activity: '活动',
    cat_museum: '美术馆·博物馆',
    cat_facility: '设施',
    cat_spot: '地点',
    /* 保存菜单及列表标签 */
    save_favorite: '收藏',
    save_want: '想去',
    save_plan: '旅行计划',
    save_star: '星标',
    list_favorite: '收藏',
    list_want: '想去',
    list_plan: '旅行计划',
    list_star: '星标',
    // 宠物个人档案：犬种选择下拉框的占位文本
    select_breed: '选择品种',
    /* 信息窗口 */
    view_details: '查看详情',
    saved_msg: '已添加到列表',
    already_saved_msg: '已在此列表中注册',
    /* 杂志相关翻译 */
    mag_issue_june: '2025年6月刊',
    mag_theme_june: '雨季与狗狗的室内时光',
    mag_desc_june: '雨天也能与爱犬一起享受的特辑',
    mag_issue_july: '2025年7月刊',
    mag_theme_july: '夏日出游 × 防晒护理',
    mag_desc_july: '介绍防紫外线和宠物友好出游地点',
    mag_event_title: '本月活动',
    mag_cafe_title: '推荐咖啡馆',
    mag_column_june: '小专栏：雨季运动不足的解决方法',
    mag_column_july: '小专栏：夏季防晒',
    mag_recommend_title: 'ちぃまめ推荐',
    mag_disaster_title: 'ちぃまめ的防灾建议',
    mag_advice_title: '小贴士',
    mag_relax_cafe_title: '与爱犬共度的休闲咖啡馆'
    ,login_greeting: '您好！'
    ,login_email: '电子邮件'
    ,login_password: '密码'
    ,login_submit: '登录'
    ,login_google: '使用 Google 登录'
    ,login_line: '使用 LINE 登录'
    ,login_no_account: '如果您没有账户'
    ,login_register_link: '请点击这里注册'
  },
  // Korean translation
  ko: {
    nav_map: '지도',
    nav_ai: 'AI',
    nav_favorites: '즐겨찾기',
    nav_magazine: '잡지',
    nav_profile: '마이페이지',
    profile_title: '마이페이지',
    magazine_title: '월간 잡지',
    map_title: '나들이 지도',
    ai_title: 'AI 어시스턴트',
    favorites_title: '즐겨찾기',
    profile_edit: '프로필 편집',
    label_name: '이름',
    label_furigana: '후리가나',
    label_email: '이메일',
    label_phone: '전화번호',
    label_address: '주소',
    label_language: '언어',
    pet_info: '반려동물 정보',
    add_pet: '반려동물 추가',
    save: '저장',
    logout: '로그아웃',
    no_favorites: '즐겨찾기가 없습니다.',
    delete: '삭제',
    // dify.html の変更に合わせて、外部AIチャットウィンドウを案内する文言に修正
    ai_intro: '아래 창에서 외부 AI 비서와 채팅하실 수 있습니다. AI 비서에게 반려동물에 대한 모든 질문을 언제든지 물어보실 수 있습니다.',
    ai_note: '*이 채팅은 목업입니다. 실제 AI 연동은 Dify가 제공하는 스크립트를 로드하세요.',
    send: '보내기',
    chat_placeholder: '메시지를 입력하세요...',
    reset_view: '주변 표시'
    ,
    /* 공유 및 추천 */
    share: '공유',
    share_x: 'X에서 공유',
    share_instagram: 'Instagram에서 공유',
    // LINE 공유 버튼 라벨
    share_line: 'LINE으로 공유',
    copy_message: '링크가 복사되었습니다. Instagram에 붙여넣어 공유하세요.',
    recommend_title: '추천 이벤트',
    /* 카테고리 명 */
    cat_event: '이벤트',
    cat_restaurant: '레스토랑',
    cat_hotel: '호텔',
    cat_activity: '활동',
    cat_museum: '미술관·박물관',
    cat_facility: '시설',
    cat_spot: '스팟',
    /* 저장 메뉴 및 리스트 라벨 */
    save_favorite: '즐겨찾기',
    save_want: '가보고 싶다',
    save_plan: '여행 계획',
    save_star: '별표',
    list_favorite: '즐겨찾기',
    list_want: '가보고 싶다',
    list_plan: '여행 계획',
    list_star: '별표',
    // 반려동물 프로필: 견종 선택 드롭다운의 플레이스홀더
    select_breed: '견종 선택',
    /* 인포 윈도우 */
    view_details: '자세히 보기',
    saved_msg: '리스트에 추가되었습니다',
    already_saved_msg: '이미 이 목록에 등록되어 있습니다',
    /* 잡지 관련 번역 */
    mag_issue_june: '2025년 6월호',
    mag_theme_june: '장마철 반려견과 실내 생활',
    mag_desc_june: '비 오는 날에도 반려견과 즐겁게 지낼 수 있는 특집',
    mag_issue_july: '2025년 7월호',
    mag_theme_july: '여름 나들이 × 자외선 케어',
    mag_desc_july: '자외선 대책과 반려견과 함께할 수 있는 나들이 장소 소개',
    mag_event_title: '이번 달 이벤트',
    mag_cafe_title: '추천 카페',
    mag_column_june: '미니 칼럼: 장마철 운동 부족 해소법',
    mag_column_july: '미니 칼럼: 여름 자외선 대책',
    mag_recommend_title: '치마메 추천',
    mag_disaster_title: '치마메의 재난 대비 팁',
    mag_advice_title: '한 포인트 조언',
    mag_relax_cafe_title: '반려견과 함께 쉬어갈 수 있는 카페'
    ,login_greeting: '안녕하세요!'
    ,login_email: '이메일 주소'
    ,login_password: '비밀번호'
    ,login_submit: '로그인'
    ,login_google: 'Google로 로그인'
    ,login_line: 'LINE으로 로그인'
    ,login_no_account: '계정이 없으신가요?'
    ,login_register_link: '여기에서 등록하세요'
  }
};

/*
  breedTranslations – 犬種の多言語対応表

  プロフィールページなどで表示する犬種名を各言語に翻訳するためのマップです。
  キーは日本語での犬種名、値は言語コードごとの翻訳文字列を保持します。
  新しい犬種を追加する場合はここにエントリを追加してください。
*/
const breedTranslations = {
  'トイ・プードル': { en: 'Toy Poodle', zh: '泰迪犬', ko: '토이 푸들' },
  'チワワ': { en: 'Chihuahua', zh: '吉娃娃', ko: '치와와' },
  '混血犬（体重10kg未満）': { en: 'Mixed breed (<10kg)', zh: '混合犬（体重10kg以下）', ko: '믹스견(10kg 미만)' },
  '柴': { en: 'Shiba Inu', zh: '柴犬', ko: '시바견' },
  'ミニチュア・ダックスフンド': { en: 'Miniature Dachshund', zh: '迷你腊肠犬', ko: '미니어처 닥스훈트' },
  'ポメラニアン': { en: 'Pomeranian', zh: '博美犬', ko: '포메라니안' },
  'ミニチュア・シュナウザー': { en: 'Miniature Schnauzer', zh: '迷你雪纳瑞', ko: '미니어처 슈나우저' },
  'ヨークシャー・テリア': { en: 'Yorkshire Terrier', zh: '约克夏梗', ko: '요크셔 테리어' },
  'フレンチ・ブルドッグ': { en: 'French Bulldog', zh: '法国斗牛犬', ko: '프렌치 불도그' },
  'マルチーズ': { en: 'Maltese', zh: '马耳他犬', ko: '말티즈' },
  'シー・ズー': { en: 'Shih Tzu', zh: '西施犬', ko: '시추' },
  'カニーンヘン・ダックスフンド': { en: 'Kanichen Dachshund', zh: '兔狩猎腊肠犬', ko: '카니헨 닥스훈트' },
  'パピヨン': { en: 'Papillon', zh: '蝴蝶犬', ko: '파피용' },
  'ゴールデン・レトリーバー': { en: 'Golden Retriever', zh: '金毛寻回犬', ko: '골든 리트리버' },
  'ウェルシュ・コーギー・ペンブローク': { en: 'Welsh Corgi Pembroke', zh: '威尔士柯基犬', ko: '웰시코기 펨브로크' },
  'ジャック・ラッセル・テリア': { en: 'Jack Russell Terrier', zh: '杰克罗素梗', ko: '잭 러셀 테리어' },
  'ラブラドール・レトリーバー': { en: 'Labrador Retriever', zh: '拉布拉多寻回犬', ko: '라브라도 리트리버' },
  'パグ': { en: 'Pug', zh: '哈巴狗', ko: '퍼그' },
  'キャバリア・キング・チャールズ・スパニエル': { en: 'Cavalier King Charles Spaniel', zh: '骑士查理王小猎犬', ko: '카발리에 킹 찰스 스패니얼' },
  'ミニチュア・ピンシャー': { en: 'Miniature Pinscher', zh: '迷你品犬', ko: '미니핀' },
  '混血犬（体重10kg以上20kg未満）': { en: 'Mixed breed (10–20kg)', zh: '混合犬（10kg以上20kg以下）', ko: '믹스견(10~20kg)' },
  'ペキニーズ': { en: 'Pekingese', zh: '北京犬', ko: '페키니즈' },
  'イタリアン・グレーハウンド': { en: 'Italian Greyhound', zh: '意大利灵缇', ko: '이탈리안 그레이하운드' },
  'ボーダー・コリー': { en: 'Border Collie', zh: '边境牧羊犬', ko: '보더 콜리' },
  'ビーグル': { en: 'Beagle', zh: '比格犬', ko: '비글' },
  'ビション・フリーゼ': { en: 'Bichon Frise', zh: '比熊犬', ko: '비숑 프리제' },
  'シェットランド・シープドッグ': { en: 'Shetland Sheepdog', zh: '喜乐蒂牧羊犬', ko: '셰틀랜드 쉽도그' },
  'ボストン・テリア': { en: 'Boston Terrier', zh: '波士顿梗', ko: '보스턴 테리어' },
  'アメリカン・コッカー・スパニエル': { en: 'American Cocker Spaniel', zh: '美国可卡', ko: '아메리칸 코커 스패니얼' },
  '日本スピッツ': { en: 'Japanese Spitz', zh: '日本尖嘴犬', ko: '일본 스피츠' }
};

/**
 * 翻訳済みの犬種名を返します。
 * @param {string} breed 元の犬種名（日本語）
 * @param {string} lang 言語コード ('ja','en','zh','ko')
 * @returns {string} 指定言語の犬種名または入力値
 */
function translateBreed(breed, lang) {
  if (!breed) return '';
  if (lang === 'ja') return breed;
  const entry = breedTranslations[breed];
  if (entry && entry[lang]) {
    return entry[lang];
  }
  return breed;
}

// 言語設定の取得と保存
function getUserLang() {
  return localStorage.getItem('userLang') || 'ja';
}
function setUserLang(lang) {
  localStorage.setItem('userLang', lang);
}

/**
 * data-i18n-key を持つ要素に翻訳文字列を適用する
 */
function applyTranslations() {
  const lang = getUserLang();
  // テキストコンテンツの翻訳
  document.querySelectorAll('[data-i18n-key]').forEach((el) => {
    const key = el.getAttribute('data-i18n-key');
    if (translations[lang] && translations[lang][key]) {
      el.textContent = translations[lang][key];
    }
  });
  // プレースホルダー属性の翻訳
  document.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
    const key = el.getAttribute('data-i18n-placeholder');
    if (translations[lang] && translations[lang][key]) {
      el.setAttribute('placeholder', translations[lang][key]);
    }
  });

  // カテゴリフィルタバーのラベルも更新する
  if (typeof updateCategoryLabels === 'function') {
    updateCategoryLabels();
  }

  // ペット情報フォームの犬種名を更新
  if (typeof updateBreedOptions === 'function') {
    updateBreedOptions();
  }
}

// グローバルから参照できるようにエクスポート
window.translations = translations;
window.getUserLang = getUserLang;
window.applyTranslations = applyTranslations;
// 犬種の翻訳テーブルと翻訳関数も公開する
window.breedTranslations = breedTranslations;
window.translateBreed = translateBreed;

/**
 * 現在の言語を順番に切り替える
 */
function cycleLang() {
  const order = ['ja', 'en', 'zh', 'ko'];
  const current = getUserLang();
  const idx = order.indexOf(current);
  const next = order[(idx + 1) % order.length];
  setUserLang(next);
  // プロフィールページの言語選択セレクトにも反映
  const langSelect = document.getElementById('profile-language');
  if (langSelect) {
    langSelect.value = next;
  }
  // ユーザーデータにも保存
  try {
    const userStr = sessionStorage.getItem('user');
    if (userStr) {
      const userObj = JSON.parse(userStr);
      userObj.language = next;
      sessionStorage.setItem('user', JSON.stringify(userObj));
    }
  } catch (err) {
    /* ignore */
  }
  applyTranslations();

  // カテゴリフィルタバーのラベルを更新
  if (typeof updateCategoryLabels === 'function') {
    updateCategoryLabels();
  }

  // マップページでは、Google Maps API の言語パラメータを反映するためにページをリロードします。
  // これにより、地図上のラベルも選択した言語に切り替わります。
  try {
    const path = window.location.pathname || '';
    if (path.endsWith('map.html')) {
      // 少し待ってからリロードして翻訳が適用されるようにする
      setTimeout(() => {
        window.location.reload();
      }, 0);
    }
  } catch (err) {
    /* ignore */
  }
}

/**
 * 言語切替ボタンを初期化します
 */
function initLangToggle() {
  const btn = document.getElementById('lang-toggle-btn');
  if (btn) {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      cycleLang();
    });
  }
}

/*
  updateCategoryLabels – カテゴリフィルタバーのボタンラベルを現在の言語に合わせて更新する

  マップページのカテゴリボタンでは、ボタン生成後に data-i18n-key 属性が付与
  されていますが、言語切替時にラベルを再描画するため、この関数を
  applyTranslations() や cycleLang() の中から呼び出します。
*/
function updateCategoryLabels() {
  const lang = getUserLang();
  const t = translations[lang] || {};
  document.querySelectorAll('#category-bar .filter-btn span[data-i18n-key]').forEach((span) => {
    const key = span.getAttribute('data-i18n-key');
    if (t[key]) {
      span.textContent = t[key];
    }
  });
}

// グローバルに公開
window.updateCategoryLabels = updateCategoryLabels;

// 初期化: ページ読み込み時に翻訳を適用し、ボタンをセットアップ
document.addEventListener('DOMContentLoaded', () => {
  // セッション内のユーザー情報から言語設定があれば反映
  try {
    const userStr = sessionStorage.getItem('user');
    if (userStr) {
      const userObj = JSON.parse(userStr);
      if (userObj.language) {
        setUserLang(userObj.language);
      }
    }
  } catch (err) {
    /* ignore */
  }
  // プロフィールページの言語選択の同期
  const langSelect = document.getElementById('profile-language');
  if (langSelect) {
    langSelect.value = getUserLang();
    langSelect.addEventListener('change', () => {
      const selected = langSelect.value;
      setUserLang(selected);
      // 更新時にセッションユーザーにも保存
      try {
        const userStr2 = sessionStorage.getItem('user');
        if (userStr2) {
          const userObj2 = JSON.parse(userStr2);
          userObj2.language = selected;
          sessionStorage.setItem('user', JSON.stringify(userObj2));
        }
      } catch (e) {
        /* ignore */
      }
      applyTranslations();
    });
  }
  applyTranslations();
  initLangToggle();
});