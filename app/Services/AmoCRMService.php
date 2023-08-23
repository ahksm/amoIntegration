<?php

namespace App\Services;

const TOKEN_FILE = __DIR__ . '/tmp/access_token.json';

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFields\CustomFieldsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Filters\EntitiesLinksFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFields\NumericCustomFieldModel;
use AmoCRM\Models\CustomFields\TextCustomFieldModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Models\TaskModel;
use DateInterval;
use DateTime;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

class AmoCRMService
{
    protected $client;

    public function __construct()
    {
        $this->client = new AmoCRMApiClient('9ac8a006-b8f0-4f3a-b92c-eddcd74953dd', 'Y1vYrmAkzXjNObqHOS6xpcSgvYTHLbIy1TVqmR3HaYqZNPBYPGTeJw1zmXS5qG21', 'http://localhost:8000/contacts');
    }

    public function auth()
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth2state'] = $state;
        if (isset($_GET['button'])) {
            echo $this->client->getOAuthClient()->getOAuthButton([
                'title' => 'Установить интеграцию',
                'compact' => true,
                'class_name' => 'className',
                'color' => 'default',
                'error_callback' => 'handleOauthError',
                'state' => $state,
            ]);
            die;
        } else {
            $authorizationUrl = $this->client->getOAuthClient()->getAuthorizeUrl([
                'state' => $state,
                'mode' => 'post_message',
            ]);
            header('Location: ' . $authorizationUrl);
            die;
        }
    }

    private function connect($request)
    {
        session_start();

        $clientId = '9ac8a006-b8f0-4f3a-b92c-eddcd74953dd';
        $clientSecret = 'Y1vYrmAkzXjNObqHOS6xpcSgvYTHLbIy1TVqmR3HaYqZNPBYPGTeJw1zmXS5qG21';
        $redirectUri = 'http://localhost:8000/contacts';

        $apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);

        if (!file_exists(TOKEN_FILE)) {
            if ($request->query('referer') !== null) {
                $apiClient->setAccountBaseDomain($request->query('referer'));
            }

            if ($request->query('code') === null) {
                $this->auth();
            }

            $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode($request->query('code'));

            if (!$accessToken->hasExpired()) {
                $this->saveToken([
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $apiClient->getAccountBaseDomain(),
                ]);
            }
        } else {
            $accessToken = $this->get_token();
            $apiClient->setAccessToken($accessToken)
                ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
                ->onAccessTokenRefresh(
                    function (AccessTokenInterface $accessToken, string $baseDomain) {
                        $this->saveToken(
                            [
                                'accessToken' => $accessToken->getToken(),
                                'refreshToken' => $accessToken->getRefreshToken(),
                                'expires' => $accessToken->getExpires(),
                                'baseDomain' => $baseDomain,
                            ]
                        );
                    }
                );
        }

        return $apiClient;
    }

    private function saveToken($accessToken)
    {
        if (
            isset($accessToken['accessToken'], $accessToken['refreshToken'], $accessToken['expires'], $accessToken['baseDomain'])
        ) {
            $data = [
                'accessToken' => $accessToken['accessToken'],
                'expires' => $accessToken['expires'],
                'refreshToken' => $accessToken['refreshToken'],
                'baseDomain' => $accessToken['baseDomain'],
            ];

            file_put_contents(TOKEN_FILE, json_encode($data));
        } else {
            exit('Invalid access token ' . var_export($accessToken, true));
        }
    }

    private function get_token()
    {
        if (!file_exists(TOKEN_FILE)) {
            exit('Access token file not found');
        }

        $accessToken = json_decode(file_get_contents(TOKEN_FILE), true);

        if (
            isset($accessToken)
            && isset($accessToken['accessToken'])
            && isset($accessToken['refreshToken'])
            && isset($accessToken['expires'])
            && isset($accessToken['baseDomain'])
        ) {
            return new AccessToken([
                'access_token' => $accessToken['accessToken'],
                'refresh_token' => $accessToken['refreshToken'],
                'expires' => $accessToken['expires'],
                'baseDomain' => $accessToken['baseDomain'],
            ]);
        } else {
            exit('Invalid access token ' . var_export($accessToken, true));
        }
    }

    public function createContact($request)
    {
        $client = $this->connect($request);
        $duplicate = $this->isDuplicate($request, $client);

        if ($duplicate) {
            $linksService = $client->links(EntityTypesInterface::CONTACTS);
            $contactsLeads = $linksService->get(new EntitiesLinksFilter([$duplicate->getId()]))->getBy('toEntityType', 'leads');

            $linkedLead = $client->leads()->getOne($contactsLeads->getToEntityId());

            if ($linkedLead->getStatusId() == 142) {
                $customer = (new CustomerModel())->setName("Покупатель {$duplicate->getFirstName()} {$duplicate->getLastName()}")
                    ->setResponsibleUserId(9962538)
                    ->setNextDate(1692777386)
                    ->setStatusId(25023138);
                $customerModel = $client->customers()->addOne($customer);
                $links = new LinksCollection();
                $links->add($duplicate);
                $client->customers()->link($customerModel, $links);

                $notesCollection = new NotesCollection();
                $commonNote = new CommonNote();
                $commonNote->setEntityId($customerModel->getId())
                    ->setText('Данный клиент уже является покупателем!');
                $notesCollection->add($commonNote);
                $leadNotesService = $client->notes(EntityTypesInterface::CUSTOMERS);
                $leadNotesService->add($notesCollection);

                return 0;
            } else return 1;
        } else {
            $contact = new ContactModel();
            $account = $client->account()->getCurrent();
            $contact->setFirstName($request['first_name'])
                ->setLastName($request['last_name'])
                ->setAccountId($account->getId());

            $this->addField($client);
            $customFields = new CustomFieldsValuesCollection();

            $phoneField = $customFields->getBy('code', 'PHONE');
            if (empty($phoneField)) {
                $phoneField = (new TextCustomFieldValuesModel())->setFieldCode('PHONE');
            }
            $phoneField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($request['phone']))
            );
            $customFields->add($phoneField);

            $emailField = $customFields->getBy('code', 'EMAIL');
            if (empty($emailField)) {
                $emailField = (new TextCustomFieldValuesModel())->setFieldCode('EMAIL');
            }
            $emailField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($request['email']))
            );
            $customFields->add($emailField);

            $genderField = $customFields->getBy('code', 'GENDER');
            if (empty($genderField)) {
                $genderField = (new TextCustomFieldValuesModel())->setFieldCode('GENDER');
            }
            $genderField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($request['gender']))
            );
            $customFields->add($genderField);

            $ageField = $customFields->getBy('code', 'AGE');
            if (empty($ageField)) {
                $ageField = (new NumericCustomFieldValuesModel())->setFieldCode('AGE');
            }
            $ageField->setValues(
                (new NumericCustomFieldValueCollection())
                    ->add((new NumericCustomFieldValueModel())->setValue($request['age']))
            );
            $customFields->add($ageField);

            $contact->setCustomFieldsValues($customFields);

            $contactModel = $client->contacts()->addOne($contact);
            $this->linkLead($contactModel, $client);
            return 2;
        }
    }

    private function isDuplicate($request, $client)
    {
        $contacts = $client->contacts()->get();
        foreach ($contacts as $contact) {
            $customFieldsValues = $contact->getCustomFieldsValues();
            if ($customFieldsValues) {
                $phoneField = $customFieldsValues->getBy('fieldCode', 'PHONE');
                if ($phoneField && $phoneField->getValues()->first()->getValue() == $request['phone']) {
                    $duplicate = $contact;
                    break;
                }
            }
        }
        if (empty($duplicate)) {
            return null;
        } else {
            return $duplicate;
        }
    }

    private function addField($client)
    {
        $fields = [
            ['GENDER', 'Пол', 30, TextCustomFieldModel::class],
            ['AGE', 'Возраст', 40, NumericCustomFieldModel::class],
        ];

        $service = $client->customFields(EntityTypesInterface::CONTACTS);
        $collection = new CustomFieldsCollection();

        foreach ($fields as $data) {
            $result = $service->get();

            if (!$result->getBy('code', $data[0])) {
                $field = new $data[3]();
                $field->setName($data[1])->setSort($data[2])->setCode($data[0]);
                $collection->add($field);
            }
        }

        if ($collection->count()) $service->add($collection);
    }

    private function linkLead($contact, $client)
    {
        $usersCollection = $client->users()->get();
        $randUser = $usersCollection[array_rand($usersCollection->toArray())];

        $lead = (new LeadModel())
            ->setName("Сделка {$contact->getFirstName()} {$contact->getLastName()}")
            ->setPrice(54321)
            ->setAccountId($contact->getAccountId())
            ->setCreatedAt((new DateTime())->getTimestamp())
            ->setResponsibleUserId($randUser->getId());

        $leadModel = $client->leads()->addOne($lead);

        $links = (new LinksCollection())->add($leadModel);
        $client->contacts()->link($contact, $links);

        $this->linkTask($leadModel, $client);
        $this->linkProduct($leadModel, $client);
    }

    private function linkTask($lead, $client)
    {
        $task = new TaskModel();
        $completeTill = strtotime("+4 days -4 hours", strtotime(date("Y-m-d", $lead->getCreatedAt())));
        if (in_array(date("w", $completeTill), [0, 6])) $completeTill += (8 - date("w", $completeTill)) * 4 * 60 * 60;

        $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_CALL)
            ->setText('Новая задача')
            ->setCompleteTill($completeTill)
            ->setDuration(9 * 60 * 60)
            ->setEntityType(EntityTypesInterface::LEADS)
            ->setEntityId($lead->getId())
            ->setResponsibleUserId($lead->getResponsibleUserId());

        $taskModel = $client->tasks()->addOne($task);
    }

    private function linkProduct($lead, $client)
    {
        $catalogName = 'Товары';
        $quantityFirstProduct = 999;
        $quantitySecondProduct = 350;

        $catalogs = $client->catalogs()->get();
        $catalog = $catalogs->getBy('name', $catalogName);
        $catalogElements = $client->catalogElements($catalog->getId())->get();

        $firstProduct = $catalogElements[0];
        $secondProduct = $catalogElements[1];

        $firstProduct->setQuantity($quantityFirstProduct);
        $secondProduct->setQuantity($quantitySecondProduct);

        $links = new LinksCollection();
        $links->add($firstProduct)->add($secondProduct);
        $client->leads()->link($lead, $links);
    }
}
