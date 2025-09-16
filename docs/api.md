# Roro REST API（抜粋仕様）

## 共通
- Namespace: `roro/v1`
- 認証: WP Nonce + Cookie（ログイン済みユーザー）
- 権限: 
  - profile: `read`/`edit_user`（本人）
  - pets: `read`（本人）、`edit_user`（本人）、`delete_user`（本人）
  - events/magazine: `manage_options` または `edit_others_posts` 相当の権限

## Profile
- `GET /profile` — 現在ユーザーの RORO_CUSTOMER + RORO_ADDRESS を取得
- `POST /profile` — 初期レコード作成（存在しない場合）
- `PUT /profile` — 氏名/電話/住所等を更新（両テーブル）
- `DELETE /profile` — 退会（ユーザー削除をトリガー; 連動削除実施）

## Pets
- `GET /pets` — 自分のペット一覧
- `POST /pets` — 追加
- `PUT /pets/{id}` — 更新
- `DELETE /pets/{id}` — 削除（is_active=false または実削除のどちらかを選択可）

## Events
- `GET /events` — 一覧（公開）
- `POST /events` — 追加（管理者）
- `PUT /events/{id}` — 更新（管理者）
- `DELETE /events/{id}` — 削除（管理者）

## Magazine
- `GET /magazine/issues` — 号一覧（公開）
- `POST /magazine/issues` — 号作成（管理）
- `PUT /magazine/issues/{id}` — 号更新（管理）
- `DELETE /magazine/issues/{id}` — 号削除（管理）
- `GET /magazine/pages?issue_id=...` — ページ一覧（公開）
- `POST /magazine/pages` / `PUT /magazine/pages/{id}` / `DELETE /magazine/pages/{id}` — 管理
- **画像**: `image_url` を優先使用（BLOB併存期はURL>データURI）

## Maintenance
- `POST /maintenance/cleanup` — アナリティクスの古い行を削除（180日超）
