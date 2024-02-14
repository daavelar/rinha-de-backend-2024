DROP TABLE IF EXISTS customers;
CREATE TABLE customers
(
    id        int unsigned not null auto_increment primary key,
    name      varchar(120) not null,
    `limit`   int          not null default 0,
    `balance` int          not null default 0
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
    id          int unsigned    not null auto_increment primary key,
    type        enum ('c', 'd') not null,
    description char(12)        not null,
    customer_id int             not null,
    value       int             not null default 0,
    created_at  timestamp       not null default current_timestamp
);