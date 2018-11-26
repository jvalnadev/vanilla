<?php
/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Dashboard\Api;

use AbstractApiController;
use ActivityModel;
use Garden\Schema\Schema;
use Garden\Web\Data;
use Garden\Web\Exception\ClientException;
use Garden\Web\Exception\NotFoundException;
use Vanilla\ApiUtils;

/**
 * Manage notifications for the current user.
 */
class NotificationsApiController extends AbstractApiController {

    /** Default limit on number of rows allowed in a page of a paginated response. */
    const PAGE_SIZE_DEFAULT = 30;

    /** @var ActivityModel */
    private $activityModel;

    /**
     * NotificationsApiController constructor.
     *
     * @param ActivityModel $activityModel
     */
    public function __construct(ActivityModel $activityModel) {
        $this->activityModel = $activityModel;
    }

    /**
     * Get a single notification.
     *
     * @param int $id
     * @return array
     * @throws \Garden\Schema\ValidationException If the request fails validation.
     * @throws \Garden\Schema\ValidationException If the response fails validation.
     * @throws \Garden\Web\Exception\HttpException If the user has a permission-based ban in their session.
     * @throws \Vanilla\Exception\PermissionException If the user does not have permission to access the notification.
     */
    public function get(int $id): array {
        $this->permission("Garden.SignIn.Allow");

        $this->idParamSchema()->setDescription("Get a single notification.");
        $out = $this->schema($this->notificationSchema(), "out");

        $row = $this->notificationByID($id);
        $this->notificationPermission($row);
        $row = $this->normalizeOutput($row);

        $result = $out->validate($row);
        return $result;
    }

    /**
     * Get an notification ID schema. Useful for documenting ID parameters in the URL path.
     *
     * @return Schema
     */
    private function idParamSchema() {
        static $schema;

        if ($schema === null) {
            $schema = $this->schema([
                "id" => "The notification ID."
            ], "in");
        }

        return $schema;
    }

    /**
     * List notifications for the current user.
     *
     * @param array $query
     * @return array
     * @throws \Garden\Schema\ValidationException If the request fails validation.
     * @throws \Garden\Schema\ValidationException If the response fails validation.
     * @throws \Garden\Web\Exception\HttpException If the user has a permission-based ban in their session.
     * @throws \Vanilla\Exception\PermissionException If the user does not have permission to access notifications.
     * @throws \Exception If unable to parse pagination input.
     */
    public function index(array $query): array {
        $this->permission("Garden.SignIn.Allow");

        $in = $this->schema([
            "page" => [
                "description" => "Page number. See [Pagination](https://docs.vanillaforums.com/apiv2/#pagination).",
                "default" => 1,
                "minimum" => 1,
            ],
        ], "in")->setDescription("List notifications for the current user.");
        $out = $this->schema([
            ":a" => $this->notificationSchema()
        ], "out");

        $query = $in->validate($query);
        list($offset, $limit) = offsetLimit("p".$query["page"], self::PAGE_SIZE_DEFAULT);

        $rows = $this->activityModel->getWhere([
            "NotifyUserID" => $this->getSession()->UserID
        ], "", "", $limit, $offset)->resultArray();
        foreach ($rows as &$row) {
            $row = $this->normalizeOutput($row);
        }

        $result = $out->validate($rows);
        return $result;
    }

    /**
     * Normalize a database record to match the API schema definition.
     *
     * @param array $row
     * @return array
     */
    private function normalizeOutput(array $row): array {
        $row["notificationID"] = $row["ActivityID"];
        $row["photoUrl"] = $row["Photo"];
        $row["read"] = $row["Notified"] === ActivityModel::SENT_OK;

        $body = formatString($row["Headline"], $row);
        // Replace anchors with bold text until notifications can be spun off from activities.
        $row["body"] = preg_replace("#<a [^>]+>(.+)</a>#Ui", "<strong>$1</strong>", $body);

        $row = ApiUtils::convertOutputKeys($row);
        return $row;
    }

    /**
     * Get a single notification by its activity ID.
     *
     * @param int $id
     * @return array
     * @throws NotFoundException If the notification could not be found.
     */
    private function notificationByID(int $id): array {
        $notification = $this->activityModel->getWhere([
            "ActivityID" => $id,
            "NotifyUserID >" => 0,
        ])->firstRow(DATASET_TYPE_ARRAY);
        if (empty($notification)) {
            throw new NotFoundException("Notification");
        }
        return $notification;
    }

    /**
     * Verify the current user has permission to access a notification.
     *
     * @param array $notification
     * @throws ClientException If the user is attempting to access a notification that is not their own.
     */
    private function notificationPermission(array $notification) {
        if ($notification["NotifyUserID"] !== $this->getSession()->UserID) {
            throw new ClientException("You do not have access to the notification(s).", 403);
        }
    }

    /**
     * Get a schema representing all available notification fields.
     *
     * @return Schema
     */
    private function notificationSchema(): Schema {
        static $schema;

        if ($schema === null) {
            $schema = $this->schema([
                "notificationID" => [
                    "description" => "A unique ID to identify the notification.",
                    "type" => "integer",
                ],
                "body" => [
                    "description" => "The notification text. This contains some HTML, but only <b> tags.",
                    "type" => "string",
                ],
                "photoUrl" => [
                    "allowNull" => true,
                    "description" => "An avatar or thumbnail associated with the notification.",
                    "type" => "string",
                ],
                "url" => [
                    "description" => "The target of the notification.",
                    "type" => "string",
                ],
                "dateInserted" => [
                    "description" => "When the notification was first made.",
                    "type" => "datetime",
                ],
                "dateUpdated" => [
                    // phpcs:ignore
                    "description" => "When the notification was last updated.\n\nNotifications on the same record will group together into a single notification, updating just the dateUpdated property.",
                    "type" => "datetime",
                ],
                "read" => [
                    "description" => "Whether or not the notification has been seen.",
                    "type" => "boolean",
                ],
            ], "NotificationSchema");
        }

        return $schema;
    }

    /**
     * Update a notification.
     *
     * @param int $id
     * @param array $body
     * @return array
     * @throws \Garden\Schema\ValidationException If the request fails validation.
     * @throws \Garden\Schema\ValidationException If the response fails validation.
     * @throws \Garden\Web\Exception\HttpException If the user has a permission-based ban in their session.
     * @throws \Vanilla\Exception\PermissionException If the user does not have permission to access the notification.
     */
    public function patch(int $id, array $body): array {
        $this->permission("Garden.SignIn.Allow");

        $this->idParamSchema();
        $in = $this->schema(Schema::parse([
            "read?" => [
                "description" => "Mark the notification read/unread.",
                "enum" => [true]
            ]
        ])->add($this->notificationSchema()), "in")->setDescription("Update a notification.");
        $out = $this->schema($this->notificationSchema(), "out");

        $body = $in->validate($body);

        $row = $this->notificationByID($id);
        $this->notificationPermission($row);

        if (array_key_exists("read", $body)) {
            $this->activityModel->markSingleRead($id);
        }

        $row = $this->notificationByID($id);
        $row = $this->normalizeOutput($row);
        $result = $out->validate($row);
        return $result;
    }

    /**
     * Update all notifications.
     *
     * @param array $body
     * @return Data
     * @throws \Garden\Schema\ValidationException If the request fails validation.
     * @throws \Garden\Schema\ValidationException If the response fails validation.
     * @throws \Garden\Web\Exception\HttpException If the user has a permission-based ban in their session.
     * @throws \Vanilla\Exception\PermissionException If the user does not have permission to access notifications.
     */
    public function patch_index(array $body): Data {
        $this->permission("Garden.SignIn.Allow");

        $in = $this->schema(Schema::parse([
            "read?" => [
                "description" => "Mark the notification read/unread.",
                "enum" => [true]
            ]
        ])->add($this->notificationSchema()), "in")->setDescription("Update all notifications.");
        $out = $this->schema([], "out");

        $body = $in->validate($body);

        if (array_key_exists("read", $body)) {
            $this->activityModel->markRead($this->getSession()->UserID);
        }

        return new Data(null, ["status" => 204]);
    }
}
