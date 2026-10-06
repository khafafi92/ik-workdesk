# Chat Room Global

The chat provides one room for each active company. Employees can access only rooms for companies assigned in the employee master. System administrators can access every active room. Users with no employee record or no assigned company cannot access any room.

## Assign companies to employees

An employee can be assigned to one or more companies in **Master Data > Employees**. Assign one company, such as KPMOG, to users who must not see the other room. Assign both APCA and KPMOG only when the employee needs both. Employees with no company selected and users without an employee record cannot open any company room. System administrators can access all active rooms.

## Enable real-time updates

The application uses Laravel Reverb for private company channels. Configure the same Reverb application ID, key, and secret for the Laravel app and Reverb server. Set these environment values on the application:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=workdesk.example.com
```

Keep the app secret on the server only. `REVERB_HOST` must be reachable by both the Laravel app and users' browsers. Set `REVERB_ALLOWED_ORIGINS` to the host names users use to open WorkDesk. For production, terminate TLS at the web server or reverse proxy and forward WebSocket upgrade requests to the Reverb server.

Install PHP dependencies, build the frontend assets, and run database migrations during deployment:

```sh
composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
```

Keep a Reverb process running alongside the web application:

```sh
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Use the deployment's process manager to restart Reverb after releases and keep it running. If Reverb is unreachable, the page reports the connection state and refreshes messages automatically until the connection returns.

## Tanya Aku with OpenAI

System administrators can configure the first AI provider at **Collaboration > Master Tanya Aku**. Enter an OpenAI model ID, API key, and the monthly internal token limit before enabling the assistant. WorkDesk encrypts the API key using the application key and never displays the saved key again. Keep `APP_KEY` backed up and private, because losing it makes encrypted settings unreadable.

Users open **Collaboration > Tanya Aku** for a private conversation. WorkDesk sends the question and recent messages from that user's conversation to OpenAI. The assistant does not search WorkDesk records or internal documents. Users should not send passwords or confidential information.

The administrator page reports prompt and completion tokens that OpenAI returns for successful requests, summed across users for the current month. The remaining percentage is an internal usage estimate, not the OpenAI account balance or official provider quota. Token usage is known only after OpenAI replies, so a final request can exceed the configured internal limit by the tokens used for that request. WorkDesk stops sending further requests once recorded usage reaches the limit. Provider availability and billing remain subject to the OpenAI account.

## Messages and attachments

Messages are plain text and can include multiple PDF, Word, Excel, JPG, or PNG files. The application does not limit the number or size of attachments. The server limits each upload request to 1 GB total. Attachments stay on the private local disk and download through a company-authorized route.
