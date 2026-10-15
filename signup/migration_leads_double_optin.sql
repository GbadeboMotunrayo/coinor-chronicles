BEGIN;
ALTER TABLE leads DROP CONSTRAINT leads_status_check;
ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status = ANY (ARRAY['pending'::text,'subscribed'::text,'unsubscribed'::text]));
ALTER TABLE leads ADD COLUMN IF NOT EXISTS token text, ADD COLUMN IF NOT EXISTS consent_at timestamptz, ADD COLUMN IF NOT EXISTS consent_text text, ADD COLUMN IF NOT EXISTS confirmed_at timestamptz, ADD COLUMN IF NOT EXISTS confirm_sent_at timestamptz, ADD COLUMN IF NOT EXISTS unsubscribed_at timestamptz;
CREATE UNIQUE INDEX IF NOT EXISTS leads_token_key ON leads(token);
COMMIT;
