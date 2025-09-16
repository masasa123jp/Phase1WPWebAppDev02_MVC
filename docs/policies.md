# 運用ポリシー（抜粋）

- **ログ保持**: `RORO_MAGAZINE_VIEW` / `...CLICK` は 180 日保持、`...DAILY` は 365 日保持を初期値とする。
- **連動削除**: `delete_user` フックで、`RORO_USER_LINK_WP` → `RORO_CUSTOMER` → `RORO_PET` → `wp_RORO_MAP_FAVORITE` を削除。
- **画像取り扱い**: 雑誌ページ画像は `image_url` を**優先**。BLOB は移行期間中の後方互換として残置。
- **権限**:

 公開情報の取得 (`GET`) は誰でも可能ですが、一覧取得に `?all=1` を指定すると管理権限が必要になります。イベント・スポット・アドバイスなど管理系データの作成・更新・削除には個別のカスタムケイパビリティを使用します。

 | エンティティ | 権限名 | 用途 |
 |---|---|---|
 | イベント | `manage_roro_events` | RORO_EVENTS_MASTER の POST/PUT/DELETE |
 | スポット | `manage_roro_spots`  | RORO_TRAVEL_SPOT_MASTER の POST/PUT/DELETE |
 | アドバイス | `manage_roro_advice` | RORO_ONE_POINT_ADVICE_MASTER の POST/PUT/DELETE |

 これらの権限は `functions.php` で `manage_options` を保持している管理者ロールに自動的に割り当てられていますが、今後は編集者など特定ユーザーへ細かく付与するために分割されています。
