<?php

declare(strict_types=1);

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
class Contractor
{
    public const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;
    public $email;
    public $mobile;

    public static function getById(int $resellerId): ?self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}

class Status
{
    public $id;
    public $name;

    public static function getName(int $id): string
    {
        $a = [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];

        return $a[$id] ?? 'Undefined';
    }
}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName)
    {
        return $_REQUEST[$pName];
    }
}

function getResellerEmailFrom(): string
{
    return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event): array
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}

function complaintEmployeeEmailSubject(array $template, int $resellerId): string
{
    return 'subject';
}

function complaintEmployeeEmailBody(array $template, int $resellerId): string
{
    return 'body';
}

function newPositionAdded(?array $data, int $resellerId): string
{
    return 'NewPositionAdded';
}

function positionStatusHasChanged(?array $data, int $resellerId): string
{
    return 'PositionStatusHasChanged';
}

class NotificationEvents
{
    public const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    public const NEW_RETURN_STATUS = 'newReturnStatus';
}

class MessagesClient
{
    public static function sendMessage(
        array $message,
        int $resellerId,
        string $returnStatus,
        ?int $clientId = null,
        ?int $differencesTo = null
    ): void {
    }
}

class NotificationManager
{
    public static function send(
        int $resellerId,
        int $clientId,
        string $returnStatus,
        int $differencesTo,
        array $template,
        ?string $error = null
    ): bool {
        return true;
    }
}
