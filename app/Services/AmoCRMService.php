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

    private function connect_to_client($request)
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

    public function createContact($contactData)
    {
        $client = $this->connect_to_client($contactData);
        $duplicate = $this->isDuplicate($contactData, $client);

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
                return 0;
            } else {
                $notesCollection = new NotesCollection();
                $commonNote = new CommonNote();
                $commonNote->setEntityId($linkedLead->getId())
                    ->setText('Для создания покупателя нужно перевести сделку в статус "Успешно реализовано"');
                $notesCollection->add($commonNote);

                $leadNotesService = $client->notes(EntityTypesInterface::LEADS);
                $leadNotesService->add($notesCollection);
                return 1;
            }
        } else {
            $contact = new ContactModel();
            $account = $client->account()->getCurrent();
            $contact->setFirstName($contactData['first_name'])
                ->setLastName($contactData['last_name'])
                ->setAccountId($account->getId());

            $this->addField($client);
            $customFields = new CustomFieldsValuesCollection();

            $phoneField = $customFields->getBy('code', 'PHONE');
            if (empty($phoneField)) {
                $phoneField = (new TextCustomFieldValuesModel())->setFieldCode('PHONE');
            }
            $phoneField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($contactData['phone']))
            );
            $customFields->add($phoneField);

            $emailField = $customFields->getBy('code', 'EMAIL');
            if (empty($emailField)) {
                $emailField = (new TextCustomFieldValuesModel())->setFieldCode('EMAIL');
            }
            $emailField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($contactData['email']))
            );
            $customFields->add($emailField);

            $genderField = $customFields->getBy('code', 'GENDER');
            if (empty($genderField)) {
                $genderField = (new TextCustomFieldValuesModel())->setFieldCode('GENDER');
            }
            $genderField->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($contactData['gender']))
            );
            $customFields->add($genderField);

            $ageField = $customFields->getBy('code', 'AGE');
            if (empty($ageField)) {
                $ageField = (new NumericCustomFieldValuesModel())->setFieldCode('AGE');
            }
            $ageField->setValues(
                (new NumericCustomFieldValueCollection())
                    ->add((new NumericCustomFieldValueModel())->setValue($contactData['age']))
            );
            $customFields->add($ageField);

            $contact->setCustomFieldsValues($customFields);

            $contactModel = $client->contacts()->addOne($contact);
            $this->linkLead($contactModel, $client);
            return 2;
        }
    }

    private function isDuplicate($contactData, $client)
    {
        foreach ($client->contacts()->get() as $contact) {
            $customFieldsValues = $contact->getCustomFieldsValues();

            if ($customFieldsValues) {
                $phoneField = $customFieldsValues->getBy('fieldCode', 'PHONE');

                if ($phoneField) {
                    $contactPhone = preg_replace('/\D/', '', $contactData['phone']);
                    $fieldPhone = preg_replace('/\D/', '', $phoneField->getValues()->first()->getValue());

                    if ($fieldPhone === $contactPhone) {
                        return $contact;
                    }
                }
            }
        }

        return null;
    }

    private function addField($apiClient)
    {
        $fields = [
            ['GENDER', 'Пол', 30, TextCustomFieldModel::class],
            ['AGE', 'Возраст', 40, NumericCustomFieldModel::class],
        ];

        $service = $apiClient->customFields(EntityTypesInterface::CONTACTS);
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

    private function linkLead($contact, $apiClient)
    {
        $usersCollection = $apiClient->users()->get();
        $randUser = $usersCollection[array_rand($usersCollection->toArray())];

        $lead = (new LeadModel())
            ->setName("Сделка {$contact->getFirstName()} {$contact->getLastName()}")
            ->setPrice(54321)
            ->setAccountId($contact->getAccountId())
            ->setCreatedAt((new DateTime())->getTimestamp())
            ->setResponsibleUserId($randUser->getId());

        $leadModel = $apiClient->leads()->addOne($lead);

        $links = (new LinksCollection())->add($leadModel);
        $apiClient->contacts()->link($contact, $links);

        $this->linkTask($leadModel, $apiClient);
        $this->linkProduct($leadModel, $apiClient);
    }

    private function linkTask($lead, $apiClient)
    {
        $now = new DateTime();
        $completeTill = (clone $now)->setTime(9, 0, 0);

        while ($completeTill->format('N') >= 6) $completeTill->add(new DateInterval('P1D'));
        $completeTill->add(new DateInterval('P4D'));

        if ($completeTill->format('N') >= 6) $completeTill->modify('next monday');
        $completeTill->setTime(18, 0, 0);

        $apiClient->tasks()->addOne((new TaskModel())->setTaskTypeId(TaskModel::TASK_TYPE_ID_CALL)->setText('Task for you!')->setCompleteTill($completeTill->getTimestamp())->setEntityType(EntityTypesInterface::LEADS)->setEntityId($lead->getId())->setResponsibleUserId($lead->getResponsibleUserId()));
    }


    private function linkProduct($lead, $apiClient)
    {
        $catalogsCollection = $apiClient->catalogs()->get();
        $catalog = $catalogsCollection->getBy('name', 'Товары');
        $catalogElementsService = $apiClient->catalogElements($catalog->getId());
        $catalogElementsCollection = $catalogElementsService->get();

        $firstProduct = $catalogElementsCollection->first();
        $firstProduct->setQuantity(999);
        $secondProduct = $catalogElementsCollection->last();
        $secondProduct->setQuantity(350);

        $links = new LinksCollection();
        $links->add($firstProduct)->add($secondProduct);
        $apiClient->leads()->link($lead, $links);
    }
}
