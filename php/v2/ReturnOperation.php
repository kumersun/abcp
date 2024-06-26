<?php

declare(strict_types=1);

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * Метод выполняет отправку уведомлений (email, sms) сотрудникам и клиенту при операции возврата товаров.
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        $resellerId = (int)$data['resellerId'];
        $clientId = (int)$data['clientId'];
        $notificationType = (int)$data['notificationType'];
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        if (empty($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';

            return $result;
        }

        if (empty($notificationType)) {
            throw new \RuntimeException('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId);
        if (null === $reseller) {
            throw new \RuntimeException('Seller not found!', 400);
        }

        $client = Contractor::getById($clientId);
        if (null === $client || Contractor::TYPE_CUSTOMER !== $client->type || $client->Seller->id !== $resellerId) {
            throw new \RuntimeException('Client not found!', 400);
        }

        $clientName = $client->getFullName() ?: $client->name;

        $cr = Employee::getById((int)$data['creatorId']);
        if (null === $cr) {
            throw new \RuntimeException('Creator not found!', 400);
        }

        $et = Employee::getById((int)$data['expertId']);
        if (null === $et) {
            throw new \RuntimeException('Expert not found!', 400);
        }

        $differences = '';
        if (static::TYPE_NEW === $notificationType) {
            $differences = newPositionAdded(null, $resellerId);
        } elseif (static::TYPE_CHANGE === $notificationType && !empty($data['differences'])) {
            $differences = positionStatusHasChanged([
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => (int)$data['creatorId'],
            'CREATOR_NAME' => $cr->getFullName(),
            'EXPERT_ID' => (int)$data['expertId'],
            'EXPERT_NAME' => $et->getFullName(),
            'CLIENT_ID' => $clientId,
            'CLIENT_NAME' => $clientName,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \RuntimeException("Template Data ($key) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        'subject' => complaintEmployeeEmailSubject($templateData, $resellerId),
                        'message' => complaintEmployeeEmailBody($templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;
            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if (static::TYPE_CHANGE === $notificationType && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo' => $client->email,
                        'subject' => complaintEmployeeEmailSubject($templateData, $resellerId),
                        'message' => complaintEmployeeEmailBody($templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS, $client->id, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $error = null;
                $res = NotificationManager::send(
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    (int)$data['differences']['to'],
                    $templateData,
                    $error
                );

                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }

                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
