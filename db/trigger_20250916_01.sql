/* ============================================================
   トリガー1: RORO_CATEGORY_DATA_LINK_MASTER への AFTER INSERT
   目的   : is_current=1 が挿入された場合、同じカテゴリ＆ペット種の
            既存レコードの is_current を 0 に更新する
   ============================================================ */
DELIMITER //
CREATE TRIGGER TR_CDL_AFTER_INS
AFTER INSERT ON RORO_CATEGORY_DATA_LINK_MASTER
FOR EACH ROW
BEGIN
  IF NEW.is_current = 1 THEN
    UPDATE RORO_CATEGORY_DATA_LINK_MASTER
       SET is_current = 0
     WHERE category_code = NEW.category_code
       AND pet_type     = NEW.pet_type
       AND NOT (CDLM_ID = NEW.CDLM_ID AND version_no = NEW.version_no);
  END IF;
END;
//
DELIMITER ;

/* ============================================================
   トリガー3: RORO_CATEGORY_DATA_LINK_MASTER への AFTER UPDATE
   目的   : is_current が 1 に変更された場合、同じカテゴリ＆ペット種の
            他レコードの is_current を 0 に更新する
   ============================================================ */
DELIMITER //
CREATE TRIGGER TR_CDL_AFTER_UPD
AFTER UPDATE ON RORO_CATEGORY_DATA_LINK_MASTER
FOR EACH ROW
BEGIN
  IF NEW.is_current = 1
     AND (
          OLD.is_current   <> NEW.is_current
       OR OLD.category_code <> NEW.category_code
       OR OLD.pet_type      <> NEW.pet_type
     )
  THEN
    UPDATE RORO_CATEGORY_DATA_LINK_MASTER
       SET is_current = 0
     WHERE category_code = NEW.category_code
       AND pet_type     = NEW.pet_type
       AND NOT (CDLM_ID = NEW.CDLM_ID AND version_no = NEW.version_no);
  END IF;
END;
//
DELIMITER ;


/* ============================================================
   トリガー2: RORO_RECOMMENDATION_LOG への BEFORE INSERT
   目的   : rec_date が NULL → 今日の日付をセット
            dedup_key が NULL または空 → 自動生成
   ============================================================ */
DELIMITER //
CREATE TRIGGER TR_RRL_BEFORE_INS
BEFORE INSERT ON RORO_RECOMMENDATION_LOG
FOR EACH ROW
BEGIN
  -- rec_date が NULL の場合は今日の日付をセット
  IF NEW.rec_date IS NULL THEN
    SET NEW.rec_date = CURRENT_DATE();
  END IF;

  -- dedup_key が未指定なら自動生成
  IF NEW.dedup_key IS NULL OR NEW.dedup_key = '' THEN
    SET NEW.dedup_key = CONCAT(
      NEW.customer_id, '|', NEW.CDLM_ID, '|',
      DATE_FORMAT(COALESCE(NEW.rec_date, CURRENT_DATE()), '%Y-%m-%d'), '|',
      NEW.rank
    );
  END IF;
END;
//
DELIMITER ;
