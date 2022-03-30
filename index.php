<?php

interface ValidatorInterface
{
    public function validate($object): bool;
}

class EmailValidator implements ValidatorInterface
{
    public function validate($object): bool
    {
        return filter_var($object, FILTER_VALIDATE_EMAIL);
    }
}

class DeviceIdValidator implements ValidatorInterface
{
    public function validate($object): bool
    {
        return (boolean)preg_match('/^([0-9A-F]{2}-){5}[0-9A-F]{2}$/', $object);
    }
}

class UniqueValidator implements ValidatorInterface
{
    private $cache = [];

    public function validate($object): bool
    {
        if (in_array($object, $this->cache)) return false;

        $this->cache[] = $object;

        return true;
    }
}

class EmailNotificationUserValidator implements ValidatorInterface
{


    private ValidatorInterface $emailValidator;
    private ValidatorInterface $uniqueValidator;

    public function __construct(ValidatorInterface $emailValidator, ValidatorInterface $uniqueValidator)
    {
        $this->emailValidator = $emailValidator;
        $this->uniqueValidator = $uniqueValidator;
    }

    public function validate($object): bool
    {
        if (gettype($object) !== 'array') return false;
        if (!array_key_exists('name', $object) || !array_key_exists('email', $object)) return false;
        if (!$this->emailValidator->validate($object['email'])) return false;
        if (!$this->uniqueValidator->validate($object['email'])) return false;
        return true;
    }
}

class PushNotificationUserValidator implements ValidatorInterface
{

    private ValidatorInterface $deviceIdValidator;
    private ValidatorInterface $uniqueValidator;

    public function __construct(ValidatorInterface $deviceIdValidator, ValidatorInterface $uniqueValidator)
    {
        $this->deviceIdValidator = $deviceIdValidator;
        $this->uniqueValidator = $uniqueValidator;
    }

    public function validate($object): bool
    {
        if (gettype($object) !== 'array') return false;
        if (!array_key_exists('name', $object) || !array_key_exists('device_id', $object)) return false;
        if (!$this->deviceIdValidator->validate($object['device_id'])) return false;
        if (!$this->uniqueValidator->validate($object['device_id'])) return false;
        return true;
    }
}

interface NotificationInterface
{
    /**
     * @param $object
     * @return mixed
     * @throws Exception
     */
    public function send($object);

    public function getName(): string;
}

interface NotificationSenderInterface
{
    public function send($object);
}

class Notification implements NotificationInterface
{
    private ValidatorInterface $validator;
    private NotificationSenderInterface $sender;
    private string $name;

    public function __construct(string $name, ValidatorInterface $validator, NotificationSenderInterface $sender)
    {
        $this->validator = $validator;
        $this->sender = $sender;
        $this->name = $name;
    }

    public function send($object)
    {
        if (!$this->validator->validate($object)) {
            throw new Exception('Invalid object');
        }
        $this->sender->send($object);
    }

    public function getName(): string
    {
        return $this->name;
    }


}

class EmailNotificationSender implements NotificationSenderInterface
{
    public function send($object)
    {
        echo "Email {$object['email']} has been sent to user {$object['name']}" . PHP_EOL;
    }
}

class PushNotificationSender implements NotificationSenderInterface
{
    public function send($object)
    {
        echo "Push notification has been sent to user {$object['name']} with device_id {$object['device_id']}" . PHP_EOL;
    }
}

class Newsletter
{

    private $users = [];
    /**
     * @var Notification[]
     */
    private array $notifications;

    /**
     * @param Notification[] $notifications
     */
    public function __construct(array $notifications)
    {
        $this->notifications = $notifications;
    }

    public function loadUsers(array $users): void
    {
        $this->users = $users;
    }

    public function send(): void
    {
        foreach ($this->users as $user) {
            foreach ($this->notifications as $notification) {
                try {
                    $notification->send($user);
                } catch (Exception $exception) {
                    echo "Unable to send {$notification->getName()} failed with message '{$exception->getMessage()}'" . PHP_EOL;
                    var_dump($user);
                }
            }
        }
    }
}


class UserRepository
{
    public function getUsers(): array
    {
        return [
            [
                'name' => 'Ivan',
                'email' => 'ivan@test.com',
                'device_id' => 'B0-5A-7B-0B-32-BD'
            ],
            [
                'name' => 'Peter',
                'email' => 'peter@test.com'
            ],
            [
                'name' => 'Mark',
                'device_id' => 'B0-5A-7B-0B-32-BD'
            ],
            [
                'name' => 'Nina',
                'email' => '...'
            ],
            [
                'name' => 'Luke',
                'device_id' => 'D0-F2-72-0C-DF'
            ],
            [
                'name' => 'Zerg',
                'device_id' => ''
            ],
            [
                'email' => '...',
                'device_id' => ''
            ]
        ];
    }
}

$emailNotificationUserValidator = new EmailNotificationUserValidator(new EmailValidator(), new UniqueValidator());
$pushNotificationUserValidator = new PushNotificationUserValidator(new DeviceIdValidator(), new UniqueValidator());
$userRepository = new UserRepository();

/** @var Notification[] $notifications */
$notifications = [];
$notifications[] = new Notification('Email Notification', $emailNotificationUserValidator, new EmailNotificationSender());
$notifications[] = new Notification('Push Notification', $pushNotificationUserValidator, new PushNotificationSender());

$newsletter = new Newsletter($notifications);
$newsletter->loadUsers($userRepository->getUsers());
$newsletter->send();



