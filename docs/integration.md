# 既存テンプレートへの組み込みメモ
- プロフィール画面テンプレートに以下を追記すると新フォームを表示できます：
  ```php
  locate_template('templates/partials/profile-form.php', true, false);
  locate_template('templates/partials/pets-panel.php', true, false);
  ```
- またはショートコードを利用： `[roro_profile_form]` / `[roro_pets_panel]`
