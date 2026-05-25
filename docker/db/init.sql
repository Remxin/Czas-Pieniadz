CREATE TYPE spending_currency AS ENUM (
    'USD',
    'EUR',
    'GBP',
    'PLN'
);

CREATE TYPE lifecycle_metric_type AS ENUM (
    'earnings',              -- Monthly earnings
    'work_days_per_week',    -- Work days per week
    'work_hours_per_month'   -- Work hours per month
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password TEXT NOT NULL,

    full_name VARCHAR(100),
    default_currency spending_currency NOT NULL DEFAULT 'PLN',
    
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_refresh_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    is_revoked BOOLEAN DEFAULT FALSE NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fixed_costs(
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    spending_name VARCHAR(255) NOT NULL,
    spending_value DECIMAL(10, 2) NOT NULL,
    spending_currency VARCHAR(3) NOT NULL,
    valid_from TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    valid_to TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_spendings (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    spending_name VARCHAR(255) NOT NULL,
    spending_value DECIMAL(10, 2) NOT NULL,
    spending_currency VARCHAR(3) NOT NULL,
    spending_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_metrics_history (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    metric_name lifecycle_metric_type NOT NULL,
    metric_value DECIMAL(10, 2) NOT NULL,
    
    metric_month INT NOT NULL CHECK (metric_month BETWEEN 1 AND 12),
    metric_year INT NOT NULL CHECK (metric_year > 2000),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, metric_name, metric_month, metric_year)
);

CREATE INDEX idx_refresh_tokens_user_id ON user_refresh_tokens(user_id);
CREATE INDEX idx_user_spendings_user_date ON user_spendings(user_id, spending_date);
CREATE INDEX idx_fixed_costs_user_valid ON fixed_costs(user_id, valid_from, valid_to);