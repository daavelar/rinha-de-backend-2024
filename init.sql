CREATE UNLOGGED TABLE IF NOT EXISTS customers
(
    id     SERIAL PRIMARY KEY,
    name   VARCHAR(50)       NOT NULL,
    max_limit INTEGER           NOT NULL,
    balance  INTEGER DEFAULT 0 NOT NULL
        CONSTRAINT check_limit CHECK (balance >= -max_limit)
);

CREATE UNLOGGED TABLE IF NOT EXISTS transactions
(
    id           SERIAL PRIMARY KEY,
    customer_id   INTEGER NOT NULL,
    value        INTEGER NOT NULL,
    type         CHAR(1) NOT NULL,
    description    VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX created_at_customer_id_index ON transactions (created_at, customer_id);

DO
    $$
BEGIN
INSERT INTO customers (name, max_limit)
VALUES ('o barato sai caro', 1000 * 100),
       ('zan corp ltda', 800 * 100),
       ('les cruders', 10000 * 100),
       ('padaria joia de cocaia', 100000 * 100),
       ('kid mais', 5000 * 100);
END;
$$;
