-- =====================================================================
--  StyleHub - Online Shopping Platform for Women's & Men's Products
--  Database schema + seed data (MySQL / MariaDB)
--
--  HOW TO IMPORT:
--    1) Start MySQL (XAMPP / WAMP / standalone)
--    2) Command line:  mysql -u root -p < database.sql
--       OR open phpMyAdmin and import this file.
--  Default app credentials assume: host=127.0.0.1, user=root, pass=(empty)
--  Change them in  config/config.php
-- =====================================================================

CREATE DATABASE IF NOT EXISTS stylehub
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE stylehub;

-- ---------------------------------------------------------------------
--  USERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(30)  DEFAULT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(120) NOT NULL,
  slug   VARCHAR(140) NOT NULL UNIQUE,
  gender ENUM('men','women','unisex') NOT NULL DEFAULT 'unisex'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  PRODUCTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name        VARCHAR(190) NOT NULL,
  slug        VARCHAR(200) NOT NULL UNIQUE,
  description TEXT,
  price       DECIMAL(10,2) NOT NULL,
  sale_price  DECIMAL(10,2) DEFAULT NULL,
  gender      ENUM('men','women','unisex') NOT NULL DEFAULT 'unisex',
  image       VARCHAR(500) NOT NULL,
  sizes       VARCHAR(120) DEFAULT NULL,   -- comma separated, e.g. "S,M,L,XL,XXL" or "6,7,8,9,10,11,12"
  colors      VARCHAR(200) DEFAULT NULL,   -- comma separated, e.g. "Black,White,Navy"
  stock       INT NOT NULL DEFAULT 0,
  featured    TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id)
    REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_products_gender  ON products(gender);
CREATE INDEX idx_products_cat     ON products(category_id);
CREATE INDEX idx_products_price   ON products(price);

-- ---------------------------------------------------------------------
--  ORDERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT DEFAULT NULL,
  order_number     VARCHAR(40) NOT NULL UNIQUE,
  subtotal         DECIMAL(10,2) NOT NULL,
  shipping         DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount         DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code      VARCHAR(40)   DEFAULT NULL,
  total            DECIMAL(10,2) NOT NULL,
  status           ENUM('pending','processing','shipped','delivered','cancelled')
                     NOT NULL DEFAULT 'pending',
  payment_method   VARCHAR(40) NOT NULL DEFAULT 'cod',
  payment_status   ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  payment_ref      VARCHAR(120) DEFAULT NULL,
  shipping_name    VARCHAR(150) NOT NULL,
  shipping_email   VARCHAR(190) NOT NULL,
  shipping_phone   VARCHAR(30)  NOT NULL,
  shipping_address VARCHAR(255) NOT NULL,
  shipping_city    VARCHAR(120) NOT NULL,
  shipping_state   VARCHAR(120) DEFAULT NULL,
  shipping_zip     VARCHAR(20)  DEFAULT NULL,
  shipping_country VARCHAR(120) NOT NULL DEFAULT 'India',
  notes            TEXT,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  ORDER ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT NOT NULL,
  product_id   INT DEFAULT NULL,
  product_name VARCHAR(190) NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  qty          INT NOT NULL,
  subtotal     DECIMAL(10,2) NOT NULL,
  size         VARCHAR(20) DEFAULT NULL,
  color        VARCHAR(40) DEFAULT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id)
    REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  PRODUCT IMAGES (optional per-colour images)
--  A product may have one image per colour; when a colour has no row the
--  product's main `image` is used.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_images (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  color      VARCHAR(40) DEFAULT NULL,
  image      VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pimg_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_pimg_product ON product_images(product_id);

-- ---------------------------------------------------------------------
--  REVIEWS & RATINGS
--  A customer can review a product; the average rating is shown on the
--  product page and category listings.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  product_id  INT NOT NULL,
  user_id     INT DEFAULT NULL,
  author_name VARCHAR(120) NOT NULL,
  rating      TINYINT NOT NULL,
  title       VARCHAR(190) DEFAULT NULL,
  body        TEXT,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_reviews_product ON reviews(product_id, status);

-- ---------------------------------------------------------------------
--  WISHLIST  (saved-for-later products, one row per user + product)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  product_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_wishlist (user_id, product_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  COUPONS  (discount codes applied at checkout)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(40) NOT NULL UNIQUE,
  type       ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value      DECIMAL(10,2) NOT NULL,
  min_order  DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses   INT NOT NULL DEFAULT 0,          -- 0 = unlimited
  used_count INT NOT NULL DEFAULT 0,
  starts_at  DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
--  SEED DATA
-- =====================================================================
INSERT INTO categories (id, name, slug, gender) VALUES
  (1,  'Dresses',      'dresses',      'women'),
  (2,  'Tops',         'tops',         'women'),
  (3,  'Handbags',     'handbags',     'women'),
  (4,  'Women Shoes',  'women-shoes',  'women'),
  (5,  'Beauty',       'beauty',       'women'),
  (6,  'Shirts',       'shirts',       'men'),
  (7,  'T-Shirts',     't-shirts',     'men'),
  (8,  'Jeans',        'jeans',        'men'),
  (9,  'Men Shoes',    'men-shoes',    'men'),
  (10, 'Watches',      'watches',      'men');

-- Product images are generated on-the-fly by the image service below.
-- (Replace the `image` value with your own uploaded file paths in production.)
INSERT INTO products
  (category_id, name, slug, description, price, sale_price, gender, image, stock, featured) VALUES
-- Women - Dresses
(1,'Floral Wrap Midi Dress','floral-wrap-midi-dress','A breezy floral wrap dress with a flattering V-neckline and side tie. Made from soft, breathable fabric — perfect for brunches and summer evenings.',2499.00,1899.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=elegant%20floral%20print%20wrap%20midi%20dress%20on%20mannequin%2C%20studio%20product%20photo%2C%20soft%20beige%20background',25,1),
(1,'Satin Slip Evening Dress','satin-slip-evening-dress','Luxurious satin slip dress with an adjustable strap and a subtle sheen. Effortless elegance for cocktail nights.',3299.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=elegant%20emerald%20satin%20slip%20evening%20dress%2C%20studio%20product%20photography',15,0),
(1,'Boho Maxi Summer Dress','boho-maxi-summer-dress','Flowy boho maxi dress with a tiered skirt and vibrant prints. Lightweight cotton that moves with you.',1999.00,1499.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=boho%20style%20maxi%20summer%20dress%20with%20floral%20pattern%20on%20mannequin%2C%20bright%20studio%20background',30,1),
-- Women - Tops
(2,'Cotton Casual Blouse','cotton-casual-blouse','Everyday cotton blouse with a relaxed fit and button-down front. A wardrobe staple you will reach for again and again.',1299.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=white%20cotton%20casual%20blouse%20for%20women%2C%20studio%20product%20photography',40,0),
(2,'Ribbed Knit Crop Top','ribbed-knit-crop-top','Stretchy ribbed knit crop top with a flattering scoop neck. Pairs beautifully with high-waist denim.',999.00,749.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=ribbed%20knit%20crop%20top%20women%20fashion%2C%20studio%20product%20photography',35,0),
(2,'Silk Office Shirt','silk-office-shirt','Polished silk-blend shirt tailored for the workplace. Smooth drape with a crisp collar.',1799.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=elegant%20pastel%20blue%20silk%20office%20shirt%20for%20women%2C%20studio%20product%20photo',20,1),
-- Women - Handbags
(3,'Leather Tote Bag','leather-tote-bag','Roomy full-grain leather tote with a detachable pouch. Fits a laptop, keys and everything in between.',3499.00,2799.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=premium%20tan%20leather%20tote%20handbag%2C%20studio%20product%20photography',18,1),
(3,'Quilted Chain Crossbody','quilted-chain-crossbody','Timeless quilted crossbody with a gold-tone chain strap. Compact yet elegant for day to night.',2999.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=black%20quilted%20chain%20crossbody%20luxury%20bag%2C%20studio%20product%20photography',22,0),
(3,'Woven Straw Bag','woven-straw-bag','Hand-woven straw bag with a soft cotton lining. The perfect summer accessory.',1599.00,1199.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=woven%20straw%20summer%20handbag%2C%20studio%20product%20photography',26,0),
-- Women - Shoes
(4,'Classic Stiletto Heels','classic-stiletto-heels','Sleek 4-inch stiletto heels with a cushioned insole. A must-have for any special occasion.',2799.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=classic%20black%20stiletto%20high%20heels%2C%20studio%20product%20photography',24,1),
(4,'White Sneakers','white-sneakers-women','Minimalist white sneakers with a thick sole and breathable lining. Comfort that goes with everything.',2199.00,1699.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=white%20minimalist%20sneakers%20for%20women%2C%20studio%20product%20photography',45,0),
(4,'Ankle Strap Sandals','ankle-strap-sandals','Elegant ankle-strap sandals with a block heel and soft straps for all-day comfort.',1899.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=elegant%20beige%20ankle%20strap%20sandals%20women%2C%20studio%20product%20photo',30,0),
-- Women - Beauty
(5,'Matte Lipstick Set','matte-lipstick-set','A set of 5 long-lasting matte lipsticks in versatile everyday shades.',899.00,649.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=matte%20lipstick%20makeup%20set%20luxury%20cosmetics%2C%20studio%20product%20photography',60,1),
(5,'Vitamin C Face Serum','vitamin-c-face-serum','Brightening Vitamin C serum that evens skin tone and boosts radiance. 30ml.',1199.00,NULL,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=vitamin%20c%20face%20serum%20glass%20bottle%20skincare%2C%20studio%20product%20photography',50,0),
(5,'Rose Eau de Parfum','rose-eau-de-parfum','A romantic floral fragrance with notes of rose, peony and soft musk. 50ml.',2599.00,2099.00,'women','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=rose%20eau%20de%20parfum%20luxury%20perfume%20bottle%2C%20studio%20product%20photography',28,1),
-- Men - Shirts
(6,'Slim Fit Formal Shirt','slim-fit-formal-shirt','Crisp slim-fit formal shirt in wrinkle-resistant cotton. Ideal for the office and beyond.',1799.00,1399.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=slim%20fit%20white%20formal%20shirt%20for%20men%2C%20studio%20product%20photography',40,1),
(6,'Checked Casual Shirt','checked-casual-shirt','Soft brushed cotton checked shirt with a relaxed fit. Great for weekend layering.',1499.00,NULL,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=blue%20checked%20casual%20shirt%20for%20men%2C%20studio%20product%20photography',38,0),
(6,'Linen Summer Shirt','linen-summer-shirt','Breathable pure linen shirt that keeps you cool. Effortless summer style.',1699.00,1299.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=beige%20linen%20summer%20shirt%20men%2C%20studio%20product%20photography',32,0),
-- Men - T-Shirts
(7,'Premium Cotton Tee','premium-cotton-tee','Heavyweight 100% cotton tee with a clean crew neck. Pre-shrunk and built to last.',799.00,NULL,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=premium%20plain%20black%20cotton%20t-shirt%20for%20men%2C%20studio%20product%20photography',80,1),
(7,'Graphic Print Tee','graphic-print-tee','Bold graphic print tee with a modern streetwear edge. Soft, breathable jersey.',999.00,699.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=graphic%20print%20streetwear%20t-shirt%20men%2C%20studio%20product%20photography',55,0),
(7,'Polo Neck T-Shirt','polo-neck-tshirt','Classic polo with a ribbed collar and button placket. Smart-casual done right.',1199.00,NULL,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=navy%20polo%20neck%20t-shirt%20men%2C%20studio%20product%20photography',60,0),
-- Men - Jeans
(8,'Slim Fit Blue Jeans','slim-fit-blue-jeans','Stretch slim-fit jeans in a classic mid-blue wash. Comfortable, durable and versatile.',2299.00,1799.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=slim%20fit%20blue%20denim%20jeans%20for%20men%2C%20studio%20product%20photography',44,1),
(8,'Black Skinny Jeans','black-skinny-jeans','Modern skinny jeans in jet black with a hint of stretch for all-day wear.',2499.00,NULL,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=black%20skinny%20jeans%20men%2C%20studio%20product%20photography',36,0),
(8,'Distressed Denim','distressed-denim','Distressed denim with a lived-in look and reinforced stitching. Street-ready style.',2699.00,1999.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=distressed%20ripped%20denim%20jeans%20men%2C%20studio%20product%20photography',30,0),
-- Men - Shoes
(9,'Leather Oxford Shoes','leather-oxford-shoes','Handcrafted leather Oxford shoes with a welted sole. Formal elegance that lasts.',3499.00,2899.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=brown%20leather%20oxford%20dress%20shoes%20men%2C%20studio%20product%20photography',26,1),
(9,'Running Sports Shoes','running-sports-shoes','Lightweight running shoes with responsive cushioning and a breathable mesh upper.',2799.00,2199.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=black%20running%20sports%20shoes%20men%2C%20studio%20product%20photography',48,0),
(9,'Canvas Slip-Ons','canvas-slip-ons','Easy canvas slip-on shoes with a flexible sole. Your everyday go-to pair.',1499.00,NULL,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=grey%20canvas%20slip-on%20shoes%20men%2C%20studio%20product%20photography',40,0),
-- Men - Watches
(10,'Chronograph Steel Watch','chronograph-steel-watch','Precision chronograph with a stainless steel bracelet and sapphire crystal glass. 50m water resistant.',5999.00,4499.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=silver%20chronograph%20stainless%20steel%20wrist%20watch%20men%2C%20studio%20product%20photography',20,1),
(10,'Leather Strap Watch','leather-strap-watch','Classic watch with a genuine leather strap and a minimal dial. Perfect for everyday wear.',3999.00,NULL,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=classic%20brown%20leather%20strap%20wrist%20watch%20men%2C%20studio%20product%20photography',25,0),
(10,'Minimal Analog Watch','minimal-analog-watch','Ultra-slim analog watch with a clean white dial. Understated and refined.',2999.00,2399.00,'men','https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=minimal%20white%20dial%20analog%20watch%20men%2C%20studio%20product%20photography',22,0);

-- ---------------------------------------------------------------------
--  SIZE & COLOUR VARIANT DATA
--  Clothing takes letter sizes, footwear takes numeric sizes.
--  A single design may be offered in several colours.
-- ---------------------------------------------------------------------
-- Clothing (Dresses, Tops, Shirts, T-Shirts, Jeans)
UPDATE products SET sizes = 'S,M,L,XL,XXL' WHERE category_id IN (1,2,6,7,8);
-- Footwear (Women Shoes, Men Shoes)
UPDATE products SET sizes = '6,7,8,9,10,11,12' WHERE category_id IN (4,9);

-- Colours by category
UPDATE products SET colors = 'Black,Blue,Pink,Green'   WHERE category_id = 1;  -- Dresses
UPDATE products SET colors = 'White,Black,Beige,Pink'  WHERE category_id = 2;  -- Tops
UPDATE products SET colors = 'White,Black,Beige'       WHERE category_id = 4;  -- Women Shoes
UPDATE products SET colors = 'White,Blue,Beige,Black'  WHERE category_id = 6;  -- Shirts
UPDATE products SET colors = 'Black,White,Navy,Grey'   WHERE category_id = 7;  -- T-Shirts
UPDATE products SET colors = 'Blue,Black,Grey'         WHERE category_id = 8;  -- Jeans
UPDATE products SET colors = 'Black,Brown,White'       WHERE category_id = 9;  -- Men Shoes
-- Handbags, Beauty and Watches are single-variant (no size / colour options).

-- A few products get their own colour palette for variety
UPDATE products SET colors = 'Pink,Blue,Black'    WHERE slug = 'floral-wrap-midi-dress';
UPDATE products SET colors = 'Emerald,Black,Maroon' WHERE slug = 'satin-slip-evening-dress';
UPDATE products SET colors = 'Yellow,Blue,White'  WHERE slug = 'boho-maxi-summer-dress';

-- =====================================================================
--  DEMO USER  (email: demo@stylehub.test   password: password123)
-- =====================================================================
INSERT INTO users (name, email, password_hash, phone) VALUES
('Demo User','demo@stylehub.test','$2y$10$aL1KH6D820Nz0WALGmfLuunUyHHfSLbqn2aMYysIrCkiG8m1gJzom','9876543210');

-- =====================================================================
--  ADMIN USER  (email: admin@stylehub.test   password: admin123)
--  Sign in at  /admin/login.php
-- =====================================================================
INSERT INTO users (name, email, password_hash, phone, role) VALUES
('Store Admin','admin@stylehub.test','$2y$10$HKf9ayRQ5/WENjconM9DFeIm44NbjzhMQfFhkHv9xCf1andj/nI.G','9000000000','admin');

-- =====================================================================
--  PER-COLOUR PRODUCT IMAGES (demonstration data)
--  Selecting a colour on the product page swaps to the matching image.
-- =====================================================================
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Pink', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=pink%20floral%20print%20wrap%20midi%20dress%20on%20mannequin%2C%20studio%20product%20photo%2C%20soft%20beige%20background', 0 FROM products WHERE slug='floral-wrap-midi-dress';
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Blue', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=blue%20floral%20print%20wrap%20midi%20dress%20on%20mannequin%2C%20studio%20product%20photo%2C%20soft%20beige%20background', 0 FROM products WHERE slug='floral-wrap-midi-dress';
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Black', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=black%20floral%20print%20wrap%20midi%20dress%20on%20mannequin%2C%20studio%20product%20photo%2C%20soft%20beige%20background', 0 FROM products WHERE slug='floral-wrap-midi-dress';

INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Emerald', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=emerald%20satin%20slip%20evening%20dress%2C%20studio%20product%20photography', 0 FROM products WHERE slug='satin-slip-evening-dress';
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Black', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=black%20satin%20slip%20evening%20dress%2C%20studio%20product%20photography', 0 FROM products WHERE slug='satin-slip-evening-dress';
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Maroon', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=maroon%20satin%20slip%20evening%20dress%2C%20studio%20product%20photography', 0 FROM products WHERE slug='satin-slip-evening-dress';

INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Yellow', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=yellow%20boho%20maxi%20summer%20dress%20with%20floral%20pattern%20on%20mannequin%2C%20bright%20studio%20background', 0 FROM products WHERE slug='boho-maxi-summer-dress';
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'Blue', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=blue%20boho%20maxi%20summer%20dress%20with%20floral%20pattern%20on%20mannequin%2C%20bright%20studio%20background', 0 FROM products WHERE slug='boho-maxi-summer-dress';
INSERT INTO product_images (product_id, color, image, sort_order)
SELECT id, 'White', 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=white%20boho%20maxi%20summer%20dress%20with%20floral%20pattern%20on%20mannequin%2C%20bright%20studio%20background', 0 FROM products WHERE slug='boho-maxi-summer-dress';

-- =====================================================================
--  COUPONS (demo)
-- =====================================================================
INSERT IGNORE INTO coupons (code, type, value, min_order, max_uses, is_active) VALUES
  ('WELCOME10', 'percent', 10.00, 0.00,   0, 1),
  ('FLAT100',   'fixed',  100.00, 999.00, 0, 1),
  ('SAVE20',    'percent', 20.00, 2000.00, 100, 1);

-- =====================================================================
--  REVIEWS (demo — approved so they show on the product pages)
-- =====================================================================
INSERT INTO reviews (product_id, author_name, rating, title, body, status)
SELECT id, 'Ananya S.', 5, 'Loved it!', 'The fabric is soft and the fit is perfect. Arrived quickly too.', 'approved' FROM products WHERE slug='floral-wrap-midi-dress';
INSERT INTO reviews (product_id, author_name, rating, title, body, status)
SELECT id, 'Priya M.', 4, 'Great quality', 'Beautiful dress, slightly loose at the waist but easy to alter.', 'approved' FROM products WHERE slug='floral-wrap-midi-dress';
INSERT INTO reviews (product_id, author_name, rating, title, body, status)
SELECT id, 'Rohan K.', 5, 'Perfect for the office', 'Tailoring is sharp and the colour matches the photos.', 'approved' FROM products WHERE slug='slim-fit-formal-shirt';
INSERT INTO reviews (product_id, author_name, rating, title, body, status)
SELECT id, 'Neha T.', 5, 'So comfy', 'Wearing these everywhere. True to size.', 'approved' FROM products WHERE slug='white-sneakers-women';

