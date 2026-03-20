## WooCommerce Cardknox – Blocks Checkout Integration Guide

This guide explains how the Cardknox gateway integrates with WooCommerce Checkout Blocks, what code runs at each step, how saved cards work, and how to build/test the integration end‑to‑end.

### Prerequisites
- WordPress 6.5+
- WooCommerce (Store API/Blocks bundled)
- Cardknox plugin activated, keys configured: `Transaction Key` and `Publishable (Token) Key`
- Optional: Subscriptions (for tokenization/subscription handling)

### File map (what lives where)
- Plugin bootstrap and Block support registration
  - `woocommerce-gateway-cardknox.php`
    - Registers Block integration early (init priority 5)
    - Enqueues Block styles and iFields when Blocks checkout is detected
  - `includes/class-wc-gateway-cardknox-blocks.php`
    - WooCommerce Blocks integration class (`AbstractPaymentMethodType`)
    - Registers front-end script handle for Blocks and exposes settings/saved cards
- Classic gateway (server processing used by both classic and Blocks)
  - `includes/class-wc-gateway-cardknox.php`
    - Core gateway: form rendering (classic), script enqueue rules, request building, tokenization/saved-card handling, 3DS and response processing
- Blocks front-end (JS)
  - `blocks/src/index.js` – registers the payment method with Blocks
  - `blocks/src/cardknox-payment-method.js` – method config, label, features
  - `blocks/src/components/CardknoxPaymentForm.js` – main UI and checkout events wiring
  - `blocks/src/components/CardknoxIFields.js` – iFields iframes, inline validation, hidden token fields
  - `blocks/src/hooks/useCardknoxIFields.js` – iFields SDK setup, token retrieval
  - `blocks/src/style.css` – Block checkout styles
  - Built assets output to `blocks/build/` (`index.js`, `index.asset.php`)

## How Block registration works

1) Bootstrap (early) – register Blocks integration
- File: `woocommerce-gateway-cardknox.php`
- Hook: `init` (priority 5)
- Action: If Blocks are present, include `includes/class-wc-gateway-cardknox-blocks.php` and register with
  `woocommerce_blocks_payment_method_type_registration`.

2) Integration class – register scripts and provide data to JS
- File: `includes/class-wc-gateway-cardknox-blocks.php`
- `get_payment_method_script_handles()`
  - Registers `wc-cardknox-blocks` (built JS at `blocks/build/index.js`).
  - Ensures Cardknox iFields SDK (`cardknox-ifields`) is registered/enqueued first to avoid race conditions.
- `get_payment_method_data()`
  - Returns settings for the front-end: title/description, `supports`, `showSaveOption`, `iFieldsKey`, `softwareName`, `softwareVersion`, and `savedCards` (see Saved Cards section).

3) Front-end registry – tell Blocks about the method
- File: `blocks/src/index.js`
- Calls `registerPaymentMethod(CardknoxPaymentMethod)` from `wcBlocksRegistry`.

4) Payment method config and UI
- File: `blocks/src/cardknox-payment-method.js`
  - Defines method `name` (`cardknox`), `label`, `supports`, and the `content`/`edit` component.
  - Pulls server-provided settings using `wcSettings.getSetting('cardknox_data')`.
- File: `blocks/src/components/CardknoxPaymentForm.js`
  - Renders iFields inputs (number + CVV iframes, plus text expiry), optional “Save card” checkbox, and saved cards list (radio) when available.
  - Subscribes to Blocks’ `eventRegistration.onPaymentSetup` (or `onPaymentProcessing`) to handle Place Order.
  - On Place Order, validates expiry, obtains iFields tokens, and returns `paymentMethodData` to the Store API.

## End-to-end checkout flow with Blocks

1) Checkout page load
- The Blocks checkout renders the Cardknox method UI.
- Cardknox iFields SDK is enqueued before the method script.
- `CardknoxPaymentForm` initializes iFields via `useCardknoxIFields()` using the publishable key (`iFieldsKey`).

2) Customer interactions
- If saved cards exist, radio options are shown. Selecting one sets a token ID; selecting “Use a new card” shows iFields.
- iFields iframes collect PAN/CVV and expose tokens via hidden inputs. Expiry is collected via a text input (MM/YY pattern -> year normalized to YYYY in state).

3) Place Order – sending data to Store API
- `CardknoxPaymentForm` subscribes to the Blocks payment event and returns one of:
  - Saved card path: `{ wc_token: <token_id> }`
  - New card path: `{ cardknox_card_token, cardknox_cvv_token, cardknox_exp_month, cardknox_exp_year, cardknox_save_card, 'wc-cardknox-new-payment-method' }`

4) Server processing (common to classic and Blocks)
- File: `includes/class-wc-gateway-cardknox.php`
- `process_payment($orderId)` calls `generate_payment_request($order)`.
- `get_payment_data($postData)` assembles payment inputs by reading:
  - Store API payment_data keys (Blocks):
    - `cardknox_card_token` → `xCardNum`
    - `cardknox_cvv_token` → `xCVV`
    - `cardknox_exp_month` + `cardknox_exp_year` → `xExp` (MMYY)
  - Saved card ID:
    - `wc_token` → resolves to WooCommerce token → `xToken`
  - Classic fallbacks: `xCardNum`, `xCVV`, `xExp`
- `validate_payment_data()` ensures required fields exist (for Blocks, tokens are already validated by the SDK).
- `WC_Cardknox_API::request()` sends the request (`cc:sale` or `cc:authonly`).
- 3DS: If enabled, an approved response (`xResult === 'A'`) is processed immediately; verification flows may return `xResult === 'V'` and trigger client-side 3DS.
- On success, `process_response()` stores meta (`_cardknox_xrefnum`, masked, transaction captured flag), completes or updates order status, and empties the cart.

## Saved cards – display and storage

Display in Blocks UI
- Source: `includes/class-wc-gateway-cardknox-blocks.php#get_payment_method_data()` packs `savedCards` for the current user. Each entry contains: `token_id`, `card_type`, `last4`, `exp_month`, `exp_year`, and `masked`.
- UI: `blocks/src/components/CardknoxPaymentForm.js` renders radio options. If a saved card is chosen, the payment event returns `wc_token` and no iFields collection happens.

Saving a new card
- Front-end: When the “Save card” checkbox is enabled and selected, the payment event adds both flags:
  - `cardknox_save_card: 'yes'` and `'wc-cardknox-new-payment-method': '1'`
- Server: `save_payment()` decides to save when all are true: user is logged in, gateway’s “Saved cards” setting is enabled, and a save flag is present in Store API payment_data (or classic flags).
  - If the sale/auth response already includes `xToken`, that token is stored.
  - Otherwise, the gateway performs a fallback `cc:save` call using the same tokens/expiry from the request, then stores the returned `xToken`.
- Storage: `add_card()` creates a `WC_Payment_Token_CC` with:
  - `token` = `xToken`
  - `gateway_id` = `cardknox`
  - `card_type`, `last4`, `expiry_month`, `expiry_year`
  - Meta `cardknox_masked` for Cardknox-style masked number (derived if missing)

Saved token usage
- On subsequent orders, selecting a saved card returns `wc_token`; server maps it to `xToken` (no PAN/CVV sent again).
- Display tweaks: `woocommerce_payment_token_get_display_name` filter uses `cardknox_masked` to show a Cardknox‑style label like `VISA •••• 4xxxxxxxxxxx1111 (MM/YY)`.

## Which file runs when …

- Checkout page with Blocks renders: `blocks/build/index.js` → `CardknoxPaymentMethod` → `CardknoxPaymentForm`
- iFields initialization: `blocks/src/components/CardknoxPaymentForm.js` → `useCardknoxIFields.initializeIFields()` (loads via `cardknox-ifields`)
- User clicks Place Order (Blocks): `CardknoxPaymentForm` subscription returns `paymentMethodData` to WooCommerce Store API
- Server receives Store API request: `includes/class-wc-gateway-cardknox.php`
  - `get_payment_data()` maps Store API fields → Cardknox `x*` fields or saved `xToken`
  - `generate_payment_request()` builds the full request
  - `process_payment()` calls `WC_Cardknox_API::request()` and handles response/3DS
  - `save_payment()` and `add_card()` store tokens when requested

## Avoiding double-enqueue and classic JS on Blocks

- The gateway avoids loading classic checkout JS when Blocks are active:
  - `payment_scripts()` checks if Blocks handle `wc-cardknox-blocks` is registered/enqueued.
  - The iFields SDK is loaded only once (shared between classic and Blocks) to prevent global redeclaration errors.

## Build, test, and deploy

Build the Blocks assets (only needed if you modify files under `blocks/src/`):

```bash
cd wp-content/plugins/woocommerce-gateway-cardknox/blocks
npm install
npm run build
```

This outputs `build/index.js` and `build/index.asset.php`. The PHP integration class registers/enqueues these when the Checkout Block is present.

Smoke test scenarios
- New card, save OFF: iFields tokens captured; order succeeds; no token stored
- New card, save ON: order succeeds; token stored; appears in My Account > Payment Methods
- Saved card: select existing card; order succeeds without iFields prompts
- 3DS (if enabled): verify challenge/approval flow completes and redirects

## Store API payment_data keys (Blocks)
- New card path
  - `cardknox_card_token` (→ `xCardNum`)
  - `cardknox_cvv_token` (→ `xCVV`)
  - `cardknox_exp_month` (2 digits) and `cardknox_exp_year` (4 digits) (→ `xExp` = `MMYY`)
  - `cardknox_save_card` = `'yes' | 'no'`
  - `wc-cardknox-new-payment-method` = `'1'` when saving
- Saved card path
  - `wc_token` = WooCommerce payment token ID (server looks up and sets `xToken`)

## Notes and tips
- If you switch checkout templates between classic and Blocks, clear caches to ensure the proper scripts load.
- Ensure only one iFields SDK is loaded: the plugin takes care of this, but custom themes should not enqueue a second copy.
- Expiry is entered as text (`MM / YY`) and normalized to `xExp` server-side.


