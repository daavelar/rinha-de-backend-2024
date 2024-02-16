CREATE TABLE customers
(
    id      SERIAL PRIMARY KEY,
    name    VARCHAR                                       NOT NULL,
    "limit" INTEGER                                       NOT NULL,
    balance INTEGER CHECK (balance >= -"limit") DEFAULT 0 NOT NULL
);

CREATE TABLE transactions
(
    id          SERIAL PRIMARY KEY,
    description VARCHAR(10)                         NOT NULL,
    type        CHAR(1) CHECK (type IN ('c', 'd'))  NOT NULL,
    value       INTEGER                             NOT NULL,
    customer_id INTEGER REFERENCES customers (id),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);

DO
$$
    BEGIN
        INSERT INTO customers (name, "limit")
        VALUES ('o barato sai caro', 1000 * 100),
               ('zan corp ltda', 800 * 100),
               ('les cruders', 10000 * 100),
               ('padaria joia de cocaia', 100000 * 100),
               ('kid mais', 5000 * 100);
    END
$$;
