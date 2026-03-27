CREATE TABLE Menu_Type (
    MenuTypeID      NUMBER PRIMARY KEY,
    TypeName        VARCHAR2(50)
);

select * from menu;

ALTER table menu rename column name to menuname;
CREATE TABLE Menu (
    MenuID      NUMBER PRIMARY KEY,
    Name        VARCHAR2(200),
    MenuTypeID  NUMBER,
    FOREIGN KEY (MenuTypeID) REFERENCES Menu_Type(MenuTypeID)
);

select * from transaction;

alter table menu add PriceAlaCarte number(10,2);

alter TABLE Ingredient MODIFY IngredientName VARCHAR2(50)

select * from INGREDIENT;
CREATE TABLE Ingredient (
    IngredientID    NUMBER PRIMARY KEY,
    IngredientName  VARCHAR2(200),
    QtyOnHand       NUMBER,
    Unit            VARCHAR2(20),
    UnitCost        NUMBER(10,2)
);



CREATE TABLE Recipe (
    MenuID          NUMBER,
    IngredientID    NUMBER,
    QtyUsed         NUMBER,
    Unit            VARCHAR2(20),
    PRIMARY KEY (MenuID, IngredientID),
    FOREIGN KEY (MenuID) REFERENCES Menu(MenuID),
    FOREIGN KEY (IngredientID) REFERENCES Ingredient(IngredientID)
);


CREATE TABLE Member_Level (
    LevelID         NUMBER PRIMARY KEY,
    LevelName       VARCHAR2(10)
);

CREATE TABLE Member (
    CustomerID      NUMBER PRIMARY KEY,
    CustomerName    VARCHAR2(50),
    Address         VARCHAR2(100),
    Tel             VARCHAR2(20),
    LevelID         NUMBER,
    LineID          VARCHAR2(30),
    FOREIGN KEY (LevelID) REFERENCES Member_Level(LevelID)
);

CREATE TABLE Transaction (
    OrderID         NUMBER PRIMARY KEY,
    CustomerID      NUMBER,
    OrderDate       DATE,
    TotalPrice      NUMBER(10,2),
    OrderTime       VARCHAR2(20),
    DiscountMember  NUMBER(10,2),
    FOREIGN KEY (CustomerID) REFERENCES Member(CustomerID)
);

CREATE TABLE Order_Item (
    OrderID         NUMBER,
    MenuID          NUMBER,
    Quantity        NUMBER,
    PricePerMenu    NUMBER(10,2),
    PRIMARY KEY (OrderID, MenuID),
    FOREIGN KEY (OrderID) REFERENCES Transaction(OrderID),
    FOREIGN KEY (MenuID) REFERENCES Menu(MenuID)
);

CREATE TABLE Order_Profit (
    OrderID                 NUMBER PRIMARY KEY,
    ProfitBeforeGP          NUMBER(10,2),
    ProfitAfterGP           NUMBER(10,2),
    ProfitBuffetBeforeGP    NUMBER(10,2),
    ProfitBuffetAfterGP     NUMBER(10,2),
    FOREIGN KEY (OrderID) REFERENCES Transaction(OrderID)
);

select * from transaction;
insert into MENU_TYPE(MENUTYPEID,TYPENAME)
    values(3,'Buffet');


CREATE TABLE Menu_Type_Price (
    MenuPriceID NUMBER PRIMARY KEY,
    MenuID NUMBER(10),
    MenuTypeID NUMBER(10),
    Price NUMBER(10,2),
    GP NUMBER(10,2),
    CONSTRAINT fk_mtp_menu
        FOREIGN KEY (MenuID) REFERENCES Menu(MenuID),
    CONSTRAINT fk_mtp_menutype
        FOREIGN KEY (MenuTypeID) REFERENCES Menu_Type(MenuTypeID)
);

select * from recipe;
INSERT INTO Ingredient (IngredientID, IngredientName, QtyOnHand, Unit, UnitCost)
VALUES (2001, 'แซลม่อน', 5000, 'g', 10.68);

insert into MEMBER_LEVEL(LEVELID,LEVELNAME)
        values (1,'Silver');

 insert into ORDER_ITEM(ORDERID,MENUID,QUANTITY,PRICEPERMENU)
            values(9001,1001,5,30.00);

INSERT INTO Transaction (OrderID, CustomerID, OrderDate, TotalPrice, OrderTime, DiscountMember)
VALUES (9001, 10001, SYSDATE, 150, '12:00', 0);
truncate TABLE transaction

SELECT constraint_name, table_name, r_constraint_name
FROM user_constraints
WHERE constraint_name = 'SYS_C008325';

insert into RECIPE(MENUID,INGREDIENTID,QTYUSED,UNIT)
    values (1001,2001,1,'g');

insert into MEMBER(CUSTOMERID,CUSTOMERNAME,ADDRESS,TEL,LEVELID,LINEID)
    values (10001,'Daniel','Bangkok','0858525587','1','daniel123');


UPDATE Transaction
SET MenuTypeID = 1
WHERE OrderID = 9001;

truncate table Menu_Type;

INSERT INTO Menu_Type (MenuTypeID, TypeName)
VALUES (1, 'A la carte');

INSERT INTO Menu_Type (MenuTypeID, TypeName)
VALUES (2, 'Omakase Buffet');

INSERT INTO Menu_Type (MenuTypeID, TypeName)
VALUES (3, 'Buffet');

UPDATE TRANSACTION 
SET COURSEID = 6 
WHERE ORDERID = '9000003';

SELECT t.ORDERID, t.COURSEID, t.TOTALPRICE, mc.COURSENAME, mc.COURSEPRICE
FROM TRANSACTION t
LEFT JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
WHERE t.ORDERID = '9000003';

-- ดูว่า TOTALPRICE เท่าไหร่
SELECT ORDERID, TOTALPRICE FROM TRANSACTION WHERE ORDERID = '9000002';

-- ถ้า TOTALPRICE = 390 → COURSEID = 1 (Omakase Standard)
-- ถ้า TOTALPRICE = 490 → COURSEID = 2 (Omakase Premium)
-- ถ้า TOTALPRICE = 590 → COURSEID = 3 (Omakase Deluxe)

UPDATE TRANSACTION 
SET COURSEID = (TOTALPRICE)
WHERE ORDERID = '9000002';


UPDATE TRANSACTION
SET ORDERTIME = NULL
WHERE ORDERTIME IS NOT NULL;
COMMIT;



select * from RECIPE;
select * from Menu_Type;
select * from menu;
select * from Menu_Type_Price;
select * from Ingredient;
select * from Member_Level;
select * from Member;
select * from transaction;
select * from Order_Item;
select * from Order_Profit;
select * from MENU_COURSE;
select * from COURSE_MENU;

ALTER TABLE order_item ADD TYPE VARCHAR2(10);

SELECT * FROM order_item WHERE orderid;

SELECT column_name, data_type 
FROM user_tab_columns 
WHERE table_name = 'MENU_TYPE_PRICE'
ORDER BY column_id;

DESC ORDER_item;
DESC TRANSACTION;
ALTER TABLE Transaction
ADD MenuTypeID NUMBER;

ALTER TABLE Transaction
ADD CONSTRAINT fk_trx_menutype
    FOREIGN KEY (MenuTypeID)
    REFERENCES Menu_Type(MenuTypeID);

SELECT
  oi.orderid,
  SUM(r.QTYUSED * i.COST * oi.quantity) AS costprice
FROM
  order_item oi
  JOIN recipe r ON oi.menuid = r.menuid
  JOIN ingredient i ON r.ingredientid = i.ingredientid
GROUP BY
  oi.orderid;
---------

select table_name from user_tables;

SELECT --ก่อนหักGP
    oi.OrderID,
    SUM(oi.Quantity * oi.PricePerMenu) AS ProfitBeforeGP
FROM ORDER_ITEM oi
GROUP BY oi.OrderID;
--------



SELECT 
    oi.OrderID,

    -- กำไรก่อนหัก GP = ยอดขายรวม - ต้นทุนรวมของวัตถุดิบ
    SUM(oi.Quantity * oi.PricePerMenu) 
         AS ProfitBeforeGP,

    -- กำไรหลัง GP = (กำไรก่อน GP) * (1 - GP)
    (SUM(oi.Quantity * oi.PricePerMenu) 
        - SUM(r.QtyUsed * ing.UnitCost * oi.Quantity)) 
        * (1 - mtp.GP) AS ProfitAfterGP,

    0 AS BuffetBeforeGP,
    0 AS BuffetAfterGP

FROM ORDER_ITEM oi
JOIN TRANSACTION t
    ON oi.OrderID = t.OrderID
JOIN MENU_TYPE_PRICE mtp
    ON oi.MenuID = mtp.MenuID
   AND mtp.MenuTypeID = t.MenuTypeID     -- ★★ แก้ join ซ้ำ
JOIN RECIPE r
    ON oi.MenuID = r.MenuID
JOIN INGREDIENT ing
    ON r.IngredientID = ing.IngredientID
GROUP BY 
    oi.OrderID, mtp.GP;



SELECT owner, table_name 

FROM dba_tables 
WHERE table_name = 'INGREDIENT';


select * from INGREDIENT;

select * from v$version where banner like '%Express%';

ALTER SESSION SET CONTAINER = XEPDB1;


CREATE USER restaurant
IDENTIFIED BY "1234"
DEFAULT TABLESPACE USERS
TEMPORARY TABLESPACE TEMP
QUOTA UNLIMITED ON USERS
ACCOUNT UNLOCK;

GRANT CONNECT, RESOURCE TO restaurant;
GRANT CREATE SESSION TO restaurant;

create TABLE MENU_COURSE (
    COURSEID NUMBER PRIMARY KEY,
    MENUTYPEID NUMBER,
    COURSENAME VARCHAR2(30),
    PRICE NUMBER
)
UPDATE TRANSACTION 
SET COURSEID = 1 
WHERE ORDERID = '9000002';

SELECT t.ORDERID, t.MENUTYPEID, t.COURSEID, t.TOTALPRICE, 
       mc.COURSENAME, mc.COURSEPRICE
FROM TRANSACTION t
LEFT JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
WHERE t.ORDERID IN ('9000002', '9000003');


alter table MENU_COURSE RENAME column PRICE to COURSEPRICE;

select * from MENU_COURSE;

-- Omakase
INSERT INTO MENU_COURSE (COURSEID, MENUTYPEID, COURSENAME, COURSEPRICE) 
VALUES (1, 2, 'Omakase Standard390', 390);

INSERT INTO MENU_COURSE (COURSEID, MENUTYPEID, COURSENAME, COURSEPRICE) 
VALUES (2, 2, 'Omakase Premium490', 490);

INSERT INTO MENU_COURSE (COURSEID, MENUTYPEID, COURSENAME, COURSEPRICE) 
VALUES (3, 2, 'Omakase Deluxe590', 590);

-- 1. ลบ TRANSACTION ก่อน (เพราะมี FK ที่ชี้ไปหา MENU_COURSE)
DELETE FROM TRANSACTION WHERE MENUTYPEID = 3 OR COURSEID >= 4;
COMMIT;

-- 2. ตอนนี้ค่อยลบ MENU_COURSE ได้
DELETE FROM MENU_COURSE WHERE COURSEID >= 4;
COMMIT;

-- 3. ลบ MENU_TYPE (ถ้าต้องการ)
DELETE FROM MENU_TYPE WHERE MENUTYPEID = 3;
COMMIT;
UPDATE order_item SET TYPE = 'extra' WHERE orderid = 900002 AND menuid = 20001;
COMMIT;
desc order_item;
select * from order_item;
INSERT INTO order_item (orderid, menuid, quantity, pricepermenu, TYPE)
VALUES (900002, 20001, 2, 0, 'extra');
-- ดูโครงสร้างตาราง TRANSACTION ปัจจุบัน
DESC TRANSACTION;

-- เพิ่มคอลัมน์ COURSEID
ALTER TABLE TRANSACTION ADD COURSEID NUMBER;

-- เพิ่ม Foreign Key เชื่อมกับ MENU_COURSE
ALTER TABLE TRANSACTION 
ADD CONSTRAINT fk_transaction_course 
FOREIGN KEY (COURSEID) REFERENCES MENU_COURSE(COURSEID);

-- ลบคอลัมน์ PRICEALACARTE (ไม่มี underscore)
ALTER TABLE MENU DROP COLUMN PRICEALACARTE;

SELECT t.ORDERID, t.COURSEID, t.TOTALPRICE, mc.COURSENAME, mc.COURSEPRICE
FROM TRANSACTION t
LEFT JOIN MENU_COURSE mc ON t.COURSEID = mc.COURSEID
WHERE t.ORDERID = '9000003';



ALTER TABLE MEMBER_LEVEL 
ADD DISCOUNT NUMBER(5,2) DEFAULT 0;
UPDATE MEMBER_LEVEL SET DISCOUNT = 5.00 WHERE LEVELID = 1;  -- Silver 5%

SELECT c.CUSTOMERID, c.CUSTOMERNAME, ml.LEVELNAME, ml.DISCOUNT
FROM member c
LEFT JOIN MEMBER_LEVEL ml ON c.LEVELID = ml.LEVELID
ORDER BY c.CUSTOMERID;

select * from member;


CREATE TABLE COURSE_MENU (
    COURSEID NUMBER NOT NULL,
    MENUID NUMBER NOT NULL,
    SEQUENCE_ORDER NUMBER DEFAULT 1,
    PRIMARY KEY (COURSEID, MENUID),
    FOREIGN KEY (COURSEID) REFERENCES MENU_COURSE(COURSEID) ON DELETE CASCADE,
    FOREIGN KEY (MENUID) REFERENCES MENU(MENUID) ON DELETE CASCADE
);

select * from course_menu;
SELECT * FROM MENU ;

commit;
INSERT INTO COURSE_MENU (COURSEID, MENUID, SEQUENCE_ORDER) VALUES (1, 1, 1);
INSERT INTO COURSE_MENU (COURSEID, MENUID, SEQUENCE_ORDER) VALUES (1, 2, 2);


-- ============================================================
-- ลบคอลัมน์ PRICE_BUFFET จากตาราง MENU
-- ============================================================

-- ตรวจสอบโครงสร้างตารางก่อนลบ
DESC MENU;

-- ลบคอลัมน์ PRICE_BUFFET
ALTER TABLE MENU DROP COLUMN PRICE_BUFFET;

-- Commit
COMMIT;

-- ตรวจสอบโครงสร้างตารางหลังลบ (ต้องไม่มี PRICE_BUFFET แล้ว)
DESC MENU;

-- ตรวจสอบข้อมูลในตาราง
SELECT * FROM MENU;


-- ============================================================
-- ตรวจสอบข้อมูลก่อนแก้ไข
-- ============================================================
SELECT MENUID, MENUNAME, PRICE_ALACARTE, PRICE_OMAKASE 
FROM MENU;

-- ============================================================
-- แก้ราคา PRICE_OMAKASE เป็น 0 ทั้งหมด
-- ============================================================
UPDATE MENU 
SET PRICE_OMAKASE = 0;

-- Commit
COMMIT;

-- ============================================================
-- ตรวจสอบผลลัพธ์หลังแก้ไข
-- ============================================================
SELECT MENUID, MENUNAME, PRICE_ALACARTE, PRICE_OMAKASE 
FROM MENU;

-- ============================================================
-- ถ้าต้องการลบคอลัมน์ MENUTYPEID ด้วย (เพราะเป็น null ทั้งหมด)
-- ============================================================
ALTER TABLE MENU DROP COLUMN MENUTYPEID;
COMMIT;

-- ============================================================
-- ถ้าต้องการลบคอลัมน์ PRICE_OMAKASE ด้วย (ถ้าไม่ใช้แล้ว)
-- ============================================================
ALTER TABLE MENU DROP COLUMN PRICE_OMAKASE;
COMMIT;


-- ลบตารางเดิม
DROP TABLE COURSE_MENU;

-- สร้างใหม่ด้วย QUANTITY
CREATE TABLE COURSE_MENU (
    COURSEID NUMBER NOT NULL,
    MENUID NUMBER NOT NULL,
    QUANTITY NUMBER DEFAULT 1,
    PRIMARY KEY (COURSEID, MENUID),
    CONSTRAINT fk_course_menu_course FOREIGN KEY (COURSEID) 
        REFERENCES MENU_COURSE(COURSEID) ON DELETE CASCADE,
    CONSTRAINT fk_course_menu_menu FOREIGN KEY (MENUID) 
        REFERENCES MENU(MENUID) ON DELETE CASCADE
);

COMMIT;

select * from order_profit;
ALTER TABLE Order_Profit DROP COLUMN extra_cost;


CREATE TABLE INGREDIENT_USING (
    USINGID NUMBER PRIMARY KEY,
    INGREDIENTUSED NUMBER(10,2) DEFAULT 0,  -- จำนวนที่ใช้จริง
    DIFFERENCE NUMBER(10,2) DEFAULT 0       -- ส่วนต่าง
);



ALTER TABLE member
MODIFY address VARCHAR2(300);

describe member;

ALTER TABLE INGREDIENT ADD QRCODE VARCHAR2(100);
ALTER TABLE INGREDIENT ADD BARCODE VARCHAR2(50);
ALTER TABLE INGREDIENT ADD IMAGEPATH VARCHAR2(200);
ALTER TABLE TRANSACTION ADD QRCODE VARCHAR2(100);
ALTER TABLE TRANSACTION ADD BARCODE VARCHAR2(50);

ALTER TABLE TRANSACTION DROP COLUMN QRCODE;
ALTER TABLE TRANSACTION DROP COLUMN BARCODE;


ALTER TABLE MENU ADD IMAGEPATH VARCHAR2(255);
ALTER TABLE MENU ADD QRCODE VARCHAR2(100);
ALTER TABLE MENU ADD BARCODE VARCHAR2(50);


ALTER TABLE ORDER_ITEM ADD CHARGE_FLAG CHAR(1) DEFAULT 'N';
COMMENT ON COLUMN ORDER_ITEM.CHARGE_FLAG IS 'Y=แสดงราคา, N=แสดง Buffet';



-- เพิ่ม SECTION_NUMBER ใน ORDER_ITEM
ALTER TABLE ORDER_ITEM ADD SECTION_NUMBER NUMBER DEFAULT 1;

-- สร้างตาราง ORDER_SECTION เก็บข้อมูลแต่ละคน
CREATE TABLE ORDER_SECTION (
    ORDERID NUMBER NOT NULL,
    SECTION_NUMBER NUMBER NOT NULL,
    MENUTYPEID NUMBER NOT NULL,
    COURSEID NUMBER,
    PRIMARY KEY (ORDERID, SECTION_NUMBER),
    FOREIGN KEY (ORDERID) REFERENCES TRANSACTION(ORDERID) ON DELETE CASCADE,
    FOREIGN KEY (MENUTYPEID) REFERENCES MENU_TYPE(MENUTYPEID),
    FOREIGN KEY (COURSEID) REFERENCES MENU_COURSE(COURSEID)
);


CREATE TABLE PRODUCTS (
    PRODUCT_ID NUMBER PRIMARY KEY,
    PRODUCT_NAME VARCHAR2(100) NOT NULL,
    CATEGORY VARCHAR2(50),
    CREATED_DATE DATE DEFAULT SYSDATE
);

-- ตารางคลัง
CREATE TABLE INVENTORY (
    INVENTORY_ID NUMBER PRIMARY KEY,
    PRODUCT_ID NUMBER NOT NULL,
    WEIGHT NUMBER(10,2) NOT NULL,
    RECEIVE_DATE DATE NOT NULL,
    STATUS VARCHAR2(20) DEFAULT 'IN_STOCK',
    CREATED_DATE DATE DEFAULT SYSDATE,
    FOREIGN KEY (PRODUCT_ID) REFERENCES PRODUCTS(PRODUCT_ID)
);

-- ตารางประวัติการเบิก
CREATE TABLE WITHDRAWAL_HISTORY (
    WITHDRAWAL_ID NUMBER PRIMARY KEY,
    INVENTORY_ID NUMBER NOT NULL,
    AMOUNT NUMBER(10,2) NOT NULL,
    REASON VARCHAR2(500),
    WITHDRAW_DATE DATE DEFAULT SYSDATE,
    FOREIGN KEY (INVENTORY_ID) REFERENCES INVENTORY(INVENTORY_ID)
);

-- เพิ่มคอลัมน์ LAST_UPDATED ในตาราง INVENTORY
ALTER TABLE INVENTORY ADD LAST_UPDATED DATE;

COMMIT;

-- เพิ่มคอลัมน์ราคาในตาราง PRODUCTS
ALTER TABLE PRODUCTS ADD PRICE_PER_KG NUMBER(10,2) DEFAULT 0;

-- อัพเดทราคาตัวอย่าง
UPDATE PRODUCTS SET PRICE_PER_KG = 150.00 WHERE CATEGORY = 'หมู';
UPDATE PRODUCTS SET PRICE_PER_KG = 300.00 WHERE CATEGORY = 'เนื้อ';
COMMIT;

-- เพิ่มคอลัมน์ราคาต่อกรัม
ALTER TABLE PRODUCTS ADD PRICE_PER_GRAM NUMBER(10,2) DEFAULT 0;

-- อัพเดทราคาตัวอย่าง (ต่อกรัม)
UPDATE PRODUCTS SET PRICE_PER_GRAM = 0.15 WHERE CATEGORY = 'หมู'; -- 150 บาท/กก. = 0.15 บาท/กรัม
UPDATE PRODUCTS SET PRICE_PER_GRAM = 0.30 WHERE CATEGORY = 'เนื้อ'; -- 300 บาท/กก. = 0.30 บาท/กรัม
COMMIT;

-- สมมติว่าต้องการลบสินค้า PRODUCT_ID = 5 (เปลี่ยนเป็นเลขที่ต้องการ)

DELETE FROM PRODUCTS;

COMMIT;
-- แก้ไข CHARGE_FLAG ให้คิดเงิน
UPDATE ORDER_ITEM 
SET CHARGE_FLAG = 'Y' 
WHERE ORDERID = 900044 AND SECTION_NUMBER = 1;

COMMIT;
ALTER TABLE ORDER_SECTION ADD PERSON_COUNT NUMBER DEFAULT 1;
  ALTER TABLE TRANSACTION ADD SLIP_FILENAME VARCHAR2(255);
COMMIT;
COMMIT;
ALTER TABLE TRANSACTION ADD DEPOSIT_SLIP_FILENAME VARCHAR2(255);

CREATE TABLE INGREDIENT_DAMAGE (
    DAMAGE_ID NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    INGREDIENTID NUMBER NOT NULL,
    DAMAGED_QTY NUMBER(10,2) NOT NULL,
    DAMAGED_NOTE VARCHAR2(500),
    DAMAGED_IMAGE VARCHAR2(255),
    DAMAGED_DATE DATE DEFAULT SYSDATE,
    CONSTRAINT FK_DAMAGE_INGREDIENT FOREIGN KEY (INGREDIENTID) 
        REFERENCES INGREDIENT(INGREDIENTID) ON DELETE CASCADE
);

CREATE TABLE PREFIXES (
    PREFIX_ID NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    PREFIX_CODE VARCHAR2(5) NOT NULL UNIQUE,
    DESCRIPTION VARCHAR2(100) NOT NULL,
    CREATED_DATE DATE DEFAULT SYSDATE
);

-- 2. เพิ่มคอลัมน์ PREFIX_ID ในตาราง INVENTORY
ALTER TABLE INVENTORY ADD PREFIX_ID NUMBER;

-- 3. สร้าง Foreign Key
ALTER TABLE INVENTORY 
ADD CONSTRAINT FK_INVENTORY_PREFIX 
FOREIGN KEY (PREFIX_ID) REFERENCES PREFIXES(PREFIX_ID);

-- 4. สร้าง Index สำหรับประสิทธิภาพ
CREATE INDEX IDX_INVENTORY_PREFIX ON INVENTORY(PREFIX_ID);
CREATE INDEX IDX_PREFIX_CODE ON PREFIXES(PREFIX_CODE);

-- 5. เพิ่มข้อมูล Prefix เริ่มต้น (ตัวอย่าง)
INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION) VALUES ('INV', 'ทั่วไป');
INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION) VALUES ('PORK', 'วัตถุดิบหมู');
INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION) VALUES ('BEEF', 'วัตถุดิบเนื้อ');
INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION) VALUES ('CHK', 'วัตถุดิบไก่');
INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION) VALUES ('SEA', 'อาหารทะเล');
INSERT INTO PREFIXES (PREFIX_CODE, DESCRIPTION) VALUES ('VEG', 'ผักและผลไม้');

-- 6. อัพเดตข้อมูลเก่าให้มี PREFIX_ID (ถ้ามีข้อมูลเก่าอยู่)
-- ให้ PREFIX_ID = 1 (INV) สำหรับข้อมูลเก่าทั้งหมด
UPDATE INVENTORY 
SET PREFIX_ID = (SELECT PREFIX_ID FROM PREFIXES WHERE PREFIX_CODE = 'INV')
WHERE PREFIX_ID IS NULL;

-- 7. ทำให้ PREFIX_ID เป็น NOT NULL หลังจากอัพเดตข้อมูลเก่าแล้ว
ALTER TABLE INVENTORY MODIFY PREFIX_ID NOT NULL;

select * from INVENTORY;

CREATE TABLE INGREDIENT_TRANSACTION (
    TRANSACTION_ID NUMBER PRIMARY KEY,
    INGREDIENTID NUMBER,
    TRANSACTION_TYPE VARCHAR2(10), -- 'IN' หรือ 'OUT'
    QUANTITY NUMBER(10,2),
    TRANSACTION_NOTE VARCHAR2(500),
    TRANSACTION_IMAGE VARCHAR2(255),
    TRANSACTION_DATE DATE,
    CONSTRAINT FK_ING_TRANS FOREIGN KEY (INGREDIENTID) REFERENCES INGREDIENT(INGREDIENTID)
);

CREATE SEQUENCE TRANSACTION_SEQ START WITH 1;



-- ตารางเก็บไฟล์แนบ
CREATE TABLE INGREDIENT_ATTACHMENTS (
    ATTACHMENT_ID NUMBER PRIMARY KEY,
    INGREDIENTID NUMBER NOT NULL,
    FILE_NAME VARCHAR2(255) NOT NULL,
    FILE_PATH VARCHAR2(500) NOT NULL,
    FILE_TYPE VARCHAR2(50),
    FILE_SIZE NUMBER,
    DESCRIPTION VARCHAR2(500),
    UPLOAD_DATE DATE DEFAULT SYSDATE,
    CONSTRAINT FK_ATTACH_INGREDIENT 
        FOREIGN KEY (INGREDIENTID) 
        REFERENCES INGREDIENT(INGREDIENTID) 
        ON DELETE CASCADE
);

-- สร้าง Sequence
CREATE SEQUENCE ATTACHMENT_SEQ START WITH 1;

-- สร้าง Index
CREATE INDEX IDX_ATTACH_INGREDIENT ON INGREDIENT_ATTACHMENTS(INGREDIENTID);
CREATE INDEX IDX_ATTACH_DATE ON INGREDIENT_ATTACHMENTS(UPLOAD_DATE);

-- เพิ่มคอลัมน์ที่จำเป็น
ALTER TABLE INGREDIENT_USING ADD (
    INGREDIENTID NUMBER,
    COUNT_DATE DATE DEFAULT SYSDATE
);

-- สร้าง Foreign Key
ALTER TABLE INGREDIENT_USING 
ADD CONSTRAINT FK_USING_INGREDIENT 
FOREIGN KEY (INGREDIENTID) 
REFERENCES INGREDIENT(INGREDIENTID) 
ON DELETE CASCADE;

-- สร้าง Index
CREATE INDEX IDX_USING_INGREDIENT ON INGREDIENT_USING(INGREDIENTID);
CREATE INDEX IDX_USING_DATE ON INGREDIENT_USING(COUNT_DATE);

-- สร้าง Sequence (ถ้ายังไม่มี)
CREATE SEQUENCE INGREDIENT_USING_SEQ START WITH 1 INCREMENT BY 1;

COMMIT;
CREATE TABLE GLOBAL_ATTACHMENTS (
    ATTACHMENT_ID   NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,  -- ID อัตโนมัติ
    FILE_NAME       VARCHAR2(255) NOT NULL,
    FILE_PATH       VARCHAR2(500) NOT NULL,
    FILE_TYPE       VARCHAR2(50),
    FILE_SIZE       NUMBER,
    DESCRIPTION     VARCHAR2(500),
    UPLOAD_DATE     DATE DEFAULT SYSDATE
);

-- สร้าง Sequence สำหรับ ID (ถ้า IDENTITY ไม่ทำงานใน Oracle เวอร์ชันเก่า)
CREATE SEQUENCE GLOBAL_ATTACH_SEQ START WITH 1 INCREMENT BY 1;
COMMIT;

CREATE SEQUENCE GLOBAL_ATTACH_SEQ 
START WITH 1 
INCREMENT BY 1 
NOCACHE;

SELECT * FROM GLOBAL_ATTACHMENTS ORDER BY UPLOAD_DATE DESC;


DELETE FROM GLOBAL_ATTACHMENTS;
COMMIT;




ALTER TABLE TRANSACTION MODIFY ORDERDATE TIMESTAMP;



CREATE TABLE CASH_RESERVE (
    RESERVE_ID NUMBER PRIMARY KEY,
    TRANSACTION_TYPE VARCHAR2(10) NOT NULL, -- 'IN' หรือ 'OUT'
    AMOUNT NUMBER(10,2) NOT NULL,
    TRANSACTION_NOTE VARCHAR2(500),
    TRANSACTION_IMAGE VARCHAR2(255),
    TRANSACTION_DATE DATE DEFAULT SYSDATE,
    CONSTRAINT CHK_TRANS_TYPE CHECK (TRANSACTION_TYPE IN ('IN', 'OUT'))
);

-- สร้าง Sequence สำหรับ RESERVE_ID
CREATE SEQUENCE RESERVE_SEQ
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

DELETE FROM CASH_RESERVE;

COMMIT;


DESC COURSE_MENU;

DESC RECIPE;


SELECT MENUTYPEID, GP 
FROM MENU_TYPE_PRICE;


select * from menu_course;

ALTER TABLE MENU_COURSE ADD SORT_ORDER NUMBER DEFAULT 0;
UPDATE MENU_COURSE SET SORT_ORDER = COURSEID;

COMMIT;
