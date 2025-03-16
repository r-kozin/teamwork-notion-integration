<?php
// Secret Key for HMAC-SHA256 signature verification
$secretKey = "YOUR_GENERATED_SECRET_KEY";

// Get raw POST data
$rawPayload = file_get_contents("php://input");

// Get the signature sent by Teamwork Desk
$signature = $_SERVER['HTTP_X_DESK_SIGNATURE'] ?? '';

// Compute expected signature using HMAC-SHA256
$expectedSignature = hash_hmac('sha256', $rawPayload, $secretKey);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    echo json_encode(["message" => "Invalid signature"]);
    exit;
}

// Decode webhook payload
$data = json_decode($rawPayload, true);
$event = $_SERVER['HTTP_X_DESK_EVENT'] ?? '';

// Notion API credentials and database ID
$notionToken = "YOUR_NOTION_INTEGRATION_TOKEN";
$databaseId  = "YOUR_NOTION_DATABASE_ID";

switch ($event) {
    case 'ticket.created':
        // Extract ticket details from the payload
        $ticket = $data['ticket'] ?? [];
        $ticketName = $ticket['subject'] ?? 'No Subject';
        $ticketId = $ticket['id'] ?? 0;

        // Determine assignee
        if (isset($ticket['agent']) && !empty($ticket['agent'])) {
            $assignee = trim($ticket['agent']['firstName'] . ' ' . $ticket['agent']['lastName']);
        } else {
            $assignee = "Unassigned";
        }
        
        $assigneeId = $assignee;
        $dateCreated = $ticket['createdAt'] ?? date('c');
        $ticketLink = $ticket['link'] ?? '';
        $status = "Not started";

        // Create a new row in Notion
        $result = createNotionRow($notionToken, $databaseId, $ticketName, $assigneeId, $status, $dateCreated, $ticketLink, $ticketId);
        if ($result['http_code'] == 200 || $result['http_code'] == 201) {
            echo json_encode(["message" => "Row created successfully in Notion"]);
        } else {
            echo json_encode(["message" => "Failed to create row in Notion", "error" => $result['response']]);
        }
        exit;

    case 'ticket.assigned':
        // Extract ticket details from the payload
        $ticket = $data['ticket'] ?? [];
        $ticketId = $ticket['id'] ?? 0;

        // Get the new assignee from the payload
        if (isset($ticket['agent']) && !empty($ticket['agent'])) {
            $newAssignee = trim($ticket['agent']['firstName'] . " " . $ticket['agent']['lastName']);
        } else {
            $newAssignee = "Unassigned";
        }

        // Query Notion to find the page ID by Ticket ID
        $pageId = findNotionPageByTicketId($notionToken, $databaseId, $ticketId);
        if (!$pageId) {
            echo json_encode(["message" => "Notion page not found for Ticket ID: $ticketId"]);
            exit;
        }
        // Update the Notion row with the new assignee
        $updateResult = updateNotionRowAssignee($notionToken, $pageId, $newAssignee);
        if ($updateResult['http_code'] == 200) {
            echo json_encode(["message" => "Assignee updated successfully in Notion"]);
        } else {
            echo json_encode(["message" => "Failed to update assignee in Notion", "error" => $updateResult['response']]);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(["message" => "Unknown event type"]);
        exit;
}

// --- Notion API Integration Functions ---

function createNotionRow($notionToken, $databaseId, $ticketName, $assignee, $status, $dateCreated, $ticketLink, $ticketId)
{
    // Build the payload
    $data = [
        "parent" => [
            "database_id" => $databaseId
        ],
        "properties" => [
            "Ticket name" => [
                "title" => [
                    [
                        "text" => [
                            "content" => $ticketName
                        ]
                    ]
                ]
            ],
            "Assignee" => [
                "select" => [
                    "name" => $assignee
                ]
            ],
            "Status" => [
                "status" => [
                    "name" => $status
                ]
            ],
            "Date created" => [
                "date" => [
                    "start" => $dateCreated
                ]
            ],
            "Ticket Link" => [
                "url" => $ticketLink
            ],
            "Ticket ID" => [
                "number" => $ticketId
            ]
        ]
    ];

    $payload = json_encode($data);

    $ch = curl_init("https://api.notion.com/v1/pages");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $notionToken",
        "Content-Type: application/json",
        "Notion-Version: 2022-06-28"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        "http_code" => $httpCode,
        "response" => $response
    ];
}

function findNotionPageByTicketId($notionToken, $databaseId, $ticketId)
{
    // Query payload to filter by "Ticket ID"
    $payload = json_encode([
        "filter" => [
            "property" => "Ticket ID",
            "number" => [
                "equals" => $ticketId
            ]
        ]
    ]);


    $ch = curl_init("https://api.notion.com/v1/databases/{$databaseId}/query");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $notionToken",
        "Content-Type: application/json",
        "Notion-Version: 2022-06-28"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['results']) && count($result['results']) > 0) {
        return $result['results'][0]['id'];
    }
    return false;
}

function updateNotionRowAssignee($notionToken, $pageId, $newAssignee)
{
    $data = [
        "properties" => [
            "Assignee" => [
                "select" => [
                    "name" => $newAssignee
                ]
            ]
        ]
    ];

    $payload = json_encode($data);

    $ch = curl_init("https://api.notion.com/v1/pages/{$pageId}");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $notionToken",
        "Content-Type: application/json",
        "Notion-Version: 2022-06-28"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        "http_code" => $httpCode,
        "response" => $response
    ];
}
