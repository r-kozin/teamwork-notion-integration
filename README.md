# Teamwork Desk to Notion Webhook Integration

This PHP script integrates Teamwork Desk with Notion by processing webhook events. It validates incoming webhook requests using HMAC-SHA256 signatures and then either creates a new ticket entry or updates an existing one in a Notion database based on the event type.

---

## Overview

- **Signature Verification:**  
  Uses a secret key to validate the incoming payload's HMAC-SHA256 signature, ensuring the request is authentic.

- **Event Handling:**  
  Processes two types of events:
  - **ticket.created:**  
    Extracts ticket details and creates a new row in a Notion database.
  - **ticket.assigned:**  
    Finds an existing Notion entry by Ticket ID and updates its assignee.

- **Notion API Integration:**  
  Leverages Notion's API to add and update database entries with ticket information.

### Example GIF

![Example GIF](./example.gif)

---

## Requirements

- **PHP:** Version 7.4 or higher recommended.
- **Teamwork Desk Webhook**
- **Notion API Credentials:**  
  - Integration Token  
  - Database ID with properties configured as follows:
    - **Ticket name:** (Title)
    - **Assignee:** (Select)
    - **Status:** (Status)
    - **Date created:** (Date)
    - **Ticket Link:** (URL)
    - **Ticket ID:** (Number)

---

## Setup

1. **Configure Secrets and Tokens:**
   - Replace `YOUR_GENERATED_SECRET_KEY` with your actual HMAC secret key.
   - Replace `YOUR_NOTION_INTEGRATION_TOKEN` with your Notion integration token.
   - Replace `YOUR_NOTION_DATABASE_ID` with your Notion database ID.

2. **Webhook Endpoint:**
   - Deploy this script to a PHP-enabled web server.
   - Configure Teamwork Desk to send webhook events to the URL where this script is hosted.

3. **Notion Database Setup:**
   - Ensure your Notion database includes the following properties:
     - **Ticket name** (Title)
     - **Assignee** (Select)
     - **Status** (Status)
     - **Date created** (Date)
     - **Ticket Link** (URL)
     - **Ticket ID** (Number)

---

## How It Works

1. **Payload and Signature Extraction:**
   - The script reads the raw POST data (`php://input`) and extracts the `X-Desk-Signature` from the HTTP headers.
  
2. **Signature Validation:**
   - An expected signature is computed using the secret key and compared with the provided signature.  
   - If the signatures do not match, the script returns a 403 status with an "Invalid signature" message.

3. **Event Handling:**
   - The JSON payload is decoded, and the event type is determined from the `X-Desk-Event` header.
   - **For `ticket.created`:**
     - Ticket details (subject, ID, creation date, link, and agent) are extracted.
     - A new row is created in Notion via the `createNotionRow` function.
   - **For `ticket.assigned`:**
     - The ticket's new assignee is extracted.
     - The script searches for an existing Notion page with the ticket ID using `findNotionPageByTicketId`.
     - If found, the assignee is updated using `updateNotionRowAssignee`.

4. **Notion API Integration Functions:**
   - **createNotionRow:** Constructs and sends a request to create a new page in the Notion database.
   - **findNotionPageByTicketId:** Queries the Notion database to find an entry based on Ticket ID.
   - **updateNotionRowAssignee:** Sends a PATCH request to update the assignee field of an existing page.

---

## Running Locally with ngrok

Ngrok creates a secure tunnel to your local machine, making it easy to test webhook integrations without deploying your application publicly. Follow these steps to run the script locally:

1. **Download and Install ngrok:**
   - Visit [ngrok.com](https://ngrok.com) and follow instructions to download and install for your operating system.

2. **Start Your Local PHP Server:**
   - Open a terminal and navigate to the directory containing your PHP script.
   - Run the built-in PHP server with:
     ```bash
     php -S localhost:8000
     ```

3. **Expose Your Local Server Using ngrok:**
   - In a new terminal window, run:
     ```bash
     ngrok http 8000
     ```
   - Ngrok will provide a public URL (e.g., `https://abcd1234.ngrok.io`). Use this URL to configure your webhook.

4. **Configure Webhook Endpoint:**
   - Update your Teamwork Desk settings to point to the public ngrok URL provided.

5. **Test the Integration:**
   - Trigger a test webhook from Teamwork Desk or use a tool like Postman to send a test POST request to your ngrok URL. Verify that your local PHP server processes the request correctly.

---

## Testing and Debugging

- **Local Testing:**  
  Use tools like Postman to simulate webhook POST requests. Ensure the correct headers (`X-Desk-Signature` and `X-Desk-Event`) and a valid JSON payload are provided.

- **Error Handling:**  
  The script returns JSON responses with messages indicating success or error details. Check these responses for troubleshooting.


