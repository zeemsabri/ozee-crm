<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>OZEE CRM API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.9.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.9.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-external-api" class="tocify-header">
                <li class="tocify-item level-1" data-unique="external-api">
                    <a href="#external-api">External API</a>
                </li>
                                    <ul id="tocify-subheader-external-api" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="external-api-POSTapi-external-payment-create-session">
                                <a href="#external-api-POSTapi-external-payment-create-session">Create Checkout Session</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="external-api-POSTapi-external-payment-create-price">
                                <a href="#external-api-POSTapi-external-payment-create-price">Create Price</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="external-api-GETapi-external-payment-status--activityId-">
                                <a href="#external-api-GETapi-external-payment-status--activityId-">Get Payment Status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="external-api-GETapi-external-activities--appId-">
                                <a href="#external-api-GETapi-external-activities--appId-">Get Activities for Application

Retrieve a list of payment activities associated with a specific application.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="external-api-POSTapi-external-stripe-webhook--app_id-">
                                <a href="#external-api-POSTapi-external-stripe-webhook--app_id-">Handle Stripe Webhook</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: March 25, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>External API for payment integration and status tracking.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<pre><code>This documentation aims to provide all the information you need to work with our API.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).&lt;/aside&gt;</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include a <strong><code>X-Magic-Token</code></strong> header with the value <strong><code>"{YOUR_MAGIC_TOKEN}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Use the magic token provided to you in the <code>X-Magic-Token</code> header.</p>

        <h1 id="external-api">External API</h1>

    <p>APIs for external systems to interact with our payment and activity tracking system.</p>

                                <h2 id="external-api-POSTapi-external-payment-create-session">Create Checkout Session</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Create a new Stripe Checkout session for external application payment tracking.</p>
<h3>Example Payload: Payment Mode</h3>
<pre><code class="language-json">{
    "app_id": "ifam-quiz-central",
    "mode": "payment",
    "line_items": [
        {
            "price_data": {
                "currency": "aud",
                "product_data": {
                    "name": "Quiz Enrollment",
                    "description": "Year 6"
                },
                "unit_amount": 2500
            },
            "quantity": 1
        }
    ],
    "success_url": "https://example.com/success",
    "cancel_url": "https://example.com/cancel"
}</code></pre>
<h3>Scenario 1: Lifetime Subscription (Unless Cancelled)</h3>
<p>Standard recurring monthly billing that continues indefinitely.</p>
<pre><code class="language-json">{
    "app_id": "app-123",
    "mode": "subscription",
    "line_items": [{ "price": "price_abc_123", "quantity": 1 }],
    "success_url": "...", "cancel_url": "..."
}</code></pre>
<h3>Scenario 2: Limited Subscription (3 Months)</h3>
<p>To charge monthly but automatically cancel after 3 months, pass a Unix timestamp in <code>cancel_at</code>.</p>
<pre><code class="language-json">{
    "app_id": "app-123",
    "mode": "subscription",
    "line_items": [{ "price": "price_abc_123", "quantity": 1 }],
    "subscription_data": {
        "cancel_at": 1711432800
    },
    "success_url": "...", "cancel_url": "..."
}</code></pre>
<h3>Scenario 3: Total Amount in Installments (e.g., 4 Payments)</h3>
<p>To divide a cost into 4 parts, use a monthly price and set <code>cancel_at</code> to the date of the 4th payment.</p>
<pre><code class="language-json">{
    "app_id": "app-123",
    "mode": "subscription",
    "line_items": [{ "price": "price_25_per_month", "quantity": 1 }],
    "subscription_data": {
        "cancel_at": 1721887200
    },
    "success_url": "...", "cancel_url": "..."
}</code></pre>
<h3>Scenario 4: Deposit + Remaining Installments</h3>
<p>To charge $100 up front (deposit) and then $50/mo for 4 installments ($200 total), send two items:
a recurring price ($50/mo) and a one-time price ($100).</p>
<pre><code class="language-json">{
    "app_id": "app-123",
    "mode": "subscription",
    "line_items": [
        { "price": "price_50_per_month", "quantity": 1 },
        {
            "price_data": {
                "currency": "aud",
                "product_data": { "name": "Enrollment Deposit" },
                "unit_amount": 10000
            },
            "quantity": 1
        }
    ],
    "subscription_data": { "cancel_at": 1721887200 },
    "success_url": "...", "cancel_url": "..."
}</code></pre>

<span id="example-requests-POSTapi-external-payment-create-session">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/external/payment/create-session" \
    --header "X-Magic-Token: {YOUR_MAGIC_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"app_id\": \"app-123\",
    \"line_items\": [
        {
            \"price_data\": {
                \"currency\": \"aud\",
                \"product_data\": {
                    \"name\": \"Quiz Enrollment: Moustafa\",
                    \"description\": \"IFAM Quiz 2026 - Year 6\"
                },
                \"unit_amount\": 2500
            },
            \"quantity\": 1
        }
    ],
    \"success_url\": \"https:\\/\\/example.com\\/success\",
    \"cancel_url\": \"https:\\/\\/example.com\\/cancel\",
    \"mode\": \"payment\",
    \"metadata\": {
        \"order_id\": \"123\"
    },
    \"allow_promotion_codes\": true,
    \"subscription_data\": {
        \"trial_period_days\": 7,
        \"cancel_at\": 1711432800
    },
    \"user\": {
        \"id\": \"user-456\",
        \"name\": \"John Doe\",
        \"email\": \"john@example.com\",
        \"phone\": \"+123456789\"
    }
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/external/payment/create-session"
);

const headers = {
    "X-Magic-Token": "{YOUR_MAGIC_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "app_id": "app-123",
    "line_items": [
        {
            "price_data": {
                "currency": "aud",
                "product_data": {
                    "name": "Quiz Enrollment: Moustafa",
                    "description": "IFAM Quiz 2026 - Year 6"
                },
                "unit_amount": 2500
            },
            "quantity": 1
        }
    ],
    "success_url": "https:\/\/example.com\/success",
    "cancel_url": "https:\/\/example.com\/cancel",
    "mode": "payment",
    "metadata": {
        "order_id": "123"
    },
    "allow_promotion_codes": true,
    "subscription_data": {
        "trial_period_days": 7,
        "cancel_at": 1711432800
    },
    "user": {
        "id": "user-456",
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+123456789"
    }
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-external-payment-create-session">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Payment session created.&quot;,
    &quot;data&quot;: {
        &quot;session_id&quot;: &quot;cs_test_...&quot;,
        &quot;activity_id&quot;: 123,
        &quot;checkout_url&quot;: &quot;https://checkout.stripe.com/pay/...&quot;,
        &quot;public_key&quot;: &quot;pk_test_...&quot;,
        &quot;expires_at&quot;: &quot;2024-01-01 12:00:00&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-external-payment-create-session" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-external-payment-create-session"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-external-payment-create-session"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-external-payment-create-session" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-external-payment-create-session">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-external-payment-create-session" data-method="POST"
      data-path="api/external/payment/create-session"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-external-payment-create-session', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-external-payment-create-session"
                    onclick="tryItOut('POSTapi-external-payment-create-session');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-external-payment-create-session"
                    onclick="cancelTryOut('POSTapi-external-payment-create-session');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-external-payment-create-session"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/external/payment/create-session</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Magic-Token</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Magic-Token" class="auth-value"               data-endpoint="POSTapi-external-payment-create-session"
               value="{YOUR_MAGIC_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_MAGIC_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-external-payment-create-session"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-external-payment-create-session"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>app_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="app_id"                data-endpoint="POSTapi-external-payment-create-session"
               value="app-123"
               data-component="body">
    <br>
<p>The unique ID for the application. Example: <code>app-123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>line_items</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="line_items[0]"                data-endpoint="POSTapi-external-payment-create-session"
               data-component="body">
        <input type="text" style="display: none"
               name="line_items[1]"                data-endpoint="POSTapi-external-payment-create-session"
               data-component="body">
    <br>
<p>Required for 'payment' and 'subscription' modes. Not used for 'setup'. List of items to be purchased. Supports providing a Stripe Price ID (<code>price</code>) or defining one on the fly (<code>price_data</code>).</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>success_url</code></b>&nbsp;&nbsp;
<small>url</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="success_url"                data-endpoint="POSTapi-external-payment-create-session"
               value="https://example.com/success"
               data-component="body">
    <br>
<p>The URL to redirect to after successful payment. Example: <code>https://example.com/success</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>cancel_url</code></b>&nbsp;&nbsp;
<small>url</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="cancel_url"                data-endpoint="POSTapi-external-payment-create-session"
               value="https://example.com/cancel"
               data-component="body">
    <br>
<p>The URL to redirect to after cancelled payment. Example: <code>https://example.com/cancel</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>mode</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="mode"                data-endpoint="POSTapi-external-payment-create-session"
               value="payment"
               data-component="body">
    <br>
<p>The payment mode (payment, subscription, setup). Default: payment. Different modes require different payloads. Example: <code>payment</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>metadata</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="metadata"                data-endpoint="POSTapi-external-payment-create-session"
               value=""
               data-component="body">
    <br>
<p>Extra metadata to store with the payment.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>allow_promotion_codes</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-external-payment-create-session" style="display: none">
            <input type="radio" name="allow_promotion_codes"
                   value="true"
                   data-endpoint="POSTapi-external-payment-create-session"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-external-payment-create-session" style="display: none">
            <input type="radio" name="allow_promotion_codes"
                   value="false"
                   data-endpoint="POSTapi-external-payment-create-session"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether to enable the promotion code field on the checkout page. Default: false. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subscription_data</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="subscription_data"                data-endpoint="POSTapi-external-payment-create-session"
               value=""
               data-component="body">
    <br>
<p>Options for subscription mode. Use <code>cancel_at</code> (Unix timestamp) to set an expiration date for installments.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>user</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>
<p>The user information.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user.id"                data-endpoint="POSTapi-external-payment-create-session"
               value="user-456"
               data-component="body">
    <br>
<p>The ID of the user in the external system. Example: <code>user-456</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user.name"                data-endpoint="POSTapi-external-payment-create-session"
               value="John Doe"
               data-component="body">
    <br>
<p>The name of the user. Example: <code>John Doe</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user.email"                data-endpoint="POSTapi-external-payment-create-session"
               value="john@example.com"
               data-component="body">
    <br>
<p>The email of the user. Example: <code>john@example.com</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user.phone"                data-endpoint="POSTapi-external-payment-create-session"
               value="+123456789"
               data-component="body">
    <br>
<p>The phone number of the user. Example: <code>+123456789</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="external-api-POSTapi-external-payment-create-price">Create Price</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Create a new Stripe Price (and optionally a Product) for use in subsequent payment sessions.</p>
<h3>Example Payload: Subscription Mode</h3>
<pre><code class="language-json"> {
     "app_id": "app-123",
     "product_name": "Premium Subscription",
     "product_id": "abc_123",
     "currency": "aud",
     "unit_amount": 1000,
     "recurring_interval": "month",
     "metadata": {
         "internal_id": 999
     }
 }</code></pre>

<span id="example-requests-POSTapi-external-payment-create-price">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/external/payment/create-price" \
    --header "X-Magic-Token: {YOUR_MAGIC_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"app_id\": \"app-123\",
    \"product_name\": \"Premium Subscription\",
    \"product_id\": \"prod_123\",
    \"currency\": \"aud\",
    \"unit_amount\": 1000,
    \"recurring_interval\": \"month\",
    \"metadata\": {
        \"internal_id\": \"999\"
    }
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/external/payment/create-price"
);

const headers = {
    "X-Magic-Token": "{YOUR_MAGIC_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "app_id": "app-123",
    "product_name": "Premium Subscription",
    "product_id": "prod_123",
    "currency": "aud",
    "unit_amount": 1000,
    "recurring_interval": "month",
    "metadata": {
        "internal_id": "999"
    }
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-external-payment-create-price">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Price created successfully.&quot;,
    &quot;data&quot;: {
        &quot;price_id&quot;: &quot;price_...&quot;,
        &quot;product_id&quot;: &quot;prod_...&quot;,
        &quot;currency&quot;: &quot;aud&quot;,
        &quot;unit_amount&quot;: 1000
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-external-payment-create-price" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-external-payment-create-price"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-external-payment-create-price"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-external-payment-create-price" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-external-payment-create-price">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-external-payment-create-price" data-method="POST"
      data-path="api/external/payment/create-price"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-external-payment-create-price', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-external-payment-create-price"
                    onclick="tryItOut('POSTapi-external-payment-create-price');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-external-payment-create-price"
                    onclick="cancelTryOut('POSTapi-external-payment-create-price');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-external-payment-create-price"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/external/payment/create-price</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Magic-Token</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Magic-Token" class="auth-value"               data-endpoint="POSTapi-external-payment-create-price"
               value="{YOUR_MAGIC_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_MAGIC_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-external-payment-create-price"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-external-payment-create-price"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>app_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="app_id"                data-endpoint="POSTapi-external-payment-create-price"
               value="app-123"
               data-component="body">
    <br>
<p>The unique ID for the application. Example: <code>app-123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>product_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="product_name"                data-endpoint="POSTapi-external-payment-create-price"
               value="Premium Subscription"
               data-component="body">
    <br>
<p>Required if product_id is not provided. The name of the product. Example: <code>Premium Subscription</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>product_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="product_id"                data-endpoint="POSTapi-external-payment-create-price"
               value="prod_123"
               data-component="body">
    <br>
<p>Required if product_name is not provided. The ID of an existing Stripe Product. Example: <code>prod_123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="currency"                data-endpoint="POSTapi-external-payment-create-price"
               value="aud"
               data-component="body">
    <br>
<p>The currency for the price. Default: aud. Example: <code>aud</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>unit_amount</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="unit_amount"                data-endpoint="POSTapi-external-payment-create-price"
               value="1000"
               data-component="body">
    <br>
<p>The amount in cents. Example: <code>1000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>recurring_interval</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="recurring_interval"                data-endpoint="POSTapi-external-payment-create-price"
               value="month"
               data-component="body">
    <br>
<p>The interval for recurring payments (month, year, week, day). Example: <code>month</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>metadata</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="metadata"                data-endpoint="POSTapi-external-payment-create-price"
               value=""
               data-component="body">
    <br>
<p>Extra metadata to store with the price.</p>
        </div>
        </form>

                    <h2 id="external-api-GETapi-external-payment-status--activityId-">Get Payment Status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve the current status of a payment session by its activity ID.</p>

<span id="example-requests-GETapi-external-payment-status--activityId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/external/payment/status/architecto" \
    --header "X-Magic-Token: {YOUR_MAGIC_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/external/payment/status/architecto"
);

const headers = {
    "X-Magic-Token": "{YOUR_MAGIC_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-external-payment-status--activityId-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: {
        &quot;id&quot;: 123,
        &quot;status&quot;: &quot;completed&quot;,
        &quot;completed_at&quot;: &quot;2024-01-01 12:05:00&quot;,
        &quot;session_id&quot;: &quot;cs_test_...&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;message&quot;: &quot;Activity not found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-external-payment-status--activityId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-external-payment-status--activityId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-external-payment-status--activityId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-external-payment-status--activityId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-external-payment-status--activityId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-external-payment-status--activityId-" data-method="GET"
      data-path="api/external/payment/status/{activityId}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-external-payment-status--activityId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-external-payment-status--activityId-"
                    onclick="tryItOut('GETapi-external-payment-status--activityId-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-external-payment-status--activityId-"
                    onclick="cancelTryOut('GETapi-external-payment-status--activityId-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-external-payment-status--activityId-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/external/payment/status/{activityId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Magic-Token</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Magic-Token" class="auth-value"               data-endpoint="GETapi-external-payment-status--activityId-"
               value="{YOUR_MAGIC_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_MAGIC_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-external-payment-status--activityId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-external-payment-status--activityId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>activityId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="activityId"                data-endpoint="GETapi-external-payment-status--activityId-"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>activity_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="activity_id"                data-endpoint="GETapi-external-payment-status--activityId-"
               value="123"
               data-component="url">
    <br>
<p>The activity ID returned by create-session. Example: <code>123</code></p>
            </div>
                    </form>

                    <h2 id="external-api-GETapi-external-activities--appId-">Get Activities for Application

Retrieve a list of payment activities associated with a specific application.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-external-activities--appId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/external/activities/app-123" \
    --header "X-Magic-Token: {YOUR_MAGIC_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/external/activities/app-123"
);

const headers = {
    "X-Magic-Token": "{YOUR_MAGIC_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-external-activities--appId-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;data&quot;: [
        {
            &quot;id&quot;: 123,
            &quot;description&quot;: &quot;Stripe payment session initiated for Test App&quot;,
            &quot;properties&quot;: {
                &quot;app_id&quot;: &quot;app-123&quot;,
                &quot;status&quot;: &quot;pending&quot;,
                &quot;user&quot;: {
                    &quot;id&quot;: &quot;user-456&quot;,
                    &quot;name&quot;: &quot;John Doe&quot;
                }
            },
            &quot;created_at&quot;: &quot;2024-01-01 12:00:00&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-external-activities--appId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-external-activities--appId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-external-activities--appId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-external-activities--appId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-external-activities--appId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-external-activities--appId-" data-method="GET"
      data-path="api/external/activities/{appId}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-external-activities--appId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-external-activities--appId-"
                    onclick="tryItOut('GETapi-external-activities--appId-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-external-activities--appId-"
                    onclick="cancelTryOut('GETapi-external-activities--appId-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-external-activities--appId-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/external/activities/{appId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Magic-Token</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Magic-Token" class="auth-value"               data-endpoint="GETapi-external-activities--appId-"
               value="{YOUR_MAGIC_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>{YOUR_MAGIC_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-external-activities--appId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-external-activities--appId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>appId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="appId"                data-endpoint="GETapi-external-activities--appId-"
               value="app-123"
               data-component="url">
    <br>
<p>The application ID. Example: <code>app-123</code></p>
            </div>
                    </form>

                    <h2 id="external-api-POSTapi-external-stripe-webhook--app_id-">Handle Stripe Webhook</h2>

<p>
</p>

<p>Receive and process Stripe webhook events (checkout.session.completed, etc.).</p>

<span id="example-requests-POSTapi-external-stripe-webhook--app_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/external/stripe/webhook/app-123" \
    --header "Stripe-Signature: string required The signature from Stripe for security verification." \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/external/stripe/webhook/app-123"
);

const headers = {
    "Stripe-Signature": "string required The signature from Stripe for security verification.",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-external-stripe-webhook--app_id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;success&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-external-stripe-webhook--app_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-external-stripe-webhook--app_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-external-stripe-webhook--app_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-external-stripe-webhook--app_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-external-stripe-webhook--app_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-external-stripe-webhook--app_id-" data-method="POST"
      data-path="api/external/stripe/webhook/{app_id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-external-stripe-webhook--app_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-external-stripe-webhook--app_id-"
                    onclick="tryItOut('POSTapi-external-stripe-webhook--app_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-external-stripe-webhook--app_id-"
                    onclick="cancelTryOut('POSTapi-external-stripe-webhook--app_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-external-stripe-webhook--app_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/external/stripe/webhook/{app_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Stripe-Signature</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Stripe-Signature"                data-endpoint="POSTapi-external-stripe-webhook--app_id-"
               value="string required The signature from Stripe for security verification."
               data-component="header">
    <br>
<p>Example: <code>string required The signature from Stripe for security verification.</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-external-stripe-webhook--app_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-external-stripe-webhook--app_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>app_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="app_id"                data-endpoint="POSTapi-external-stripe-webhook--app_id-"
               value="app-123"
               data-component="url">
    <br>
<p>The unique ID for the application. Example: <code>app-123</code></p>
            </div>
                    </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
