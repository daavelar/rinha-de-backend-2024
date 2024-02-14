DROP TABLE IF EXISTS customers;
CREATE TABLE customers
(
    id      int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name    varchar(120) NOT NULL,
    `limit` int          NOT NULL DEFAULT 0,
    balance int          NOT NULL DEFAULT 0
);

INSERT INTO customers (name, `limit`)
VALUES ('o barato sai caro', 1000 * 100),
       ('zan corp ltda', 800 * 100),
       ('les cruders', 10000 * 100),
       ('padaria joia de cocaia', 100000 * 100),
       ('kid mais', 5000 * 100);

DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions
(
    id          int UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type        enum ('c', 'd') NOT NULL,
    description char(12)        NULL,
    customer_id int             NOT NULL,
    value       int             NOT NULL DEFAULT 0,
    created_at  datetime(3)     NOT NULL
);

CREATE INDEX transactions_created_at_index ON transactions (created_at DESC);
CREATE INDEX transactions_customer_id_index ON transactions (customer_id);
