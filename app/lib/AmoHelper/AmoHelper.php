<?php

namespace App\lib\AmoHelper;

use App\lib\AmoCrmApi\AmoCrmApi;
use App\lib\AmoMapping\samoletplus1\GENERAL_CONFIG;
use App\lib\BusinessLogic\Interest;
use App\lib\BusinessLogic\JK;
use App\lib\BusinessLogic\Lot;
use App\lib\BusinessLogic\Podbor;
use Generator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

class AmoHelper
{
    const ENTITY_TYPE_LEAD = 'leads';
    const ENTITY_TYPE_CONTACT = 'contacts';
    const ENTITY_TYPE_COMPANY = 'companies';
    const ENTITY_TYPES = [
        self::ENTITY_TYPE_LEAD,
        self::ENTITY_TYPE_CONTACT,
        self::ENTITY_TYPE_COMPANY,
    ];
    const ENTITIES_QUERY_LIMIT = 250;
    const ENTITIES_QUERY_DEFAULT = 50;
    private static AmoCrmApi $amo;
    private static Collection $accountUsers;

    public static function findContact(AmoContact $contact): ?AmoContact
    {
        $phones = $contact->getPhones();
        $emails = $contact->getEmails();
        if(empty($phones) && empty($emails)) return null;
        foreach($phones as $phone){
            $found = self::findContactByPhone($phone);
            if($found) return $found;
        }
        foreach($emails as $email){
            $found = self::findContactByMail($email);
            if($found) return $found;
        }
        return null;
    }

//    public static function findContactByCF(int $cf, ?string $cfv): ?AmoContact
//    {
//        if(empty($cf) || empty($cfv)) return null;
//        $contacts = self::amo()->__request('get', '/api/v4/contacts', ['query' => $cfv]);
//        foreach ($contacts->_embedded->contacts ?? [] as $contact) {
//            if (AmoHelper::getCFV($cf, $contact) === $cfv) return new AmoContact($contact);
//        }
//        return null;
//    }
    public static function findContactByMail(?string $email): ?AmoContact
    {
        if(empty($email) || strlen($email) < 5) return null;
        $contacts = self::amo()->__request('get', '/api/v4/contacts', ['query' => $email]);
        foreach ($contacts->_embedded->contacts ?? [] as $contact) {
            $contact = new AmoContact($contact);
            if (in_array($email, $contact->getEmails(), true)) return $contact;
        }
        return null;
    }
    public static function findContactByPhone(?int $phone): ?AmoContact
    {
        if(empty($phone) || strlen($phone) < 7) return null;
        $contacts = self::amo()->__request('get', '/api/v4/contacts', ['query' => $phone, 'with' => 'leads']);
        foreach ($contacts->_embedded->contacts ?? [] as $contact) {
            $contact = new AmoContact($contact);
            if(in_array($phone, $contact->getPhones())) return $contact;
        }
        return null;
    }
    public static function getContact(?int $id): ?AmoContact
    {
        return self::getContactsById([$id])->current();
//        $contact = self::amo()->__request('get', "/api/v4/contacts/$id", ['with' => 'leads']);
//        return $contact ? new AmoContact($contact) : null;
    }
    public static function getLead(?int $id): ?AmoLead
    {
        return self::getLeadsById([$id])->current();
//        $lead = self::amo()->__request('get', "/api/v4/leads/$id", ['with' => 'contacts']);
//        return $lead ? new AmoLead($lead) : null;
    }
    public static function getLot(?int $id): ?Lot
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        return empty((array)($lot = new Lot(new AmoLead(json_decode($lead->getJSON()))))) ? null : $lot;
    }
    public static function getInterest(?int $id): ?Interest
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        return empty((array)($interest = new Interest(new AmoLead(json_decode($lead->getJSON()))))) ? null : $interest;
    }
    public static function getJK(?int $id): ?JK
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        return empty((array)($jk = new JK(new AmoLead(json_decode($lead->getJSON()))))) ? null : $jk;
    }
    public static function getPodbor(?int $id): ?Podbor
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        return empty((array)($podbor = new Podbor(new AmoLead(json_decode($lead->getJSON()))))) ? null : $podbor;
    }
    private static function amo(): AmoCrmApi
    {
        return self::$amo ?? self::$amo = new AmoCrmApi(GENERAL_CONFIG::CLIENT_ID);
    }

    /**
     * @param array $entitiesIds
     * @param string $entityType
     * @return Generator<AmoEntity>
     */
    private static function getEntitiesById(array $entitiesIds, string $entityType) : Generator
    {
        if(!in_array($entityType, self::ENTITY_TYPES)) throw new InvalidArgumentException("Received unknown entity type: $entityType");
        $entitiesIds = array_filter($entitiesIds, function($entityId){ return is_numeric($entityId); });
        //todo выбрасывать Warning если в массиве найдены не numeric
        foreach(array_chunk($entitiesIds, self::ENTITIES_QUERY_LIMIT) as $entitiesIdsChunk){
            //todo тут можно через массив и http_build_query
            $filter = implode('&', array_map(function($entityId){ return "filter[id][]=$entityId"; }, $entitiesIdsChunk));
            $filter .= '&with=leads,contacts,tags';
            $amoResponse = self::amo()->__request('get', "/api/v4/$entityType?$filter");
            foreach($amoResponse->_embedded->$entityType ?? [] as $entity){
                if($entityType === self::ENTITY_TYPE_LEAD) yield new AmoLead($entity);
                if($entityType === self::ENTITY_TYPE_CONTACT) yield new AmoContact($entity);
//                if($entityType === self::ENTITY_TYPE_COMPANY) yield new AmoLead($entity);
            }
        }
    }

    public static function createEntity(AmoEntity $entity): void
    {
        //todo в данный момент мы возвращаем тот же объект, просто добавляем ему id созданного.
        // возможно правильнее будет получать из амо новый объект по id, там будут иными несколько полей (например created_at, updated_at)
        // т.к. мы их не передаем
        try{
            $entityType = $entity->getEntityType();
            $response = self::amo()->__request('post', "/api/v4/$entityType", [$entity->refreshId()->getUpdateStructureV4()]);
            $createdEntityID = $response->_embedded->{$entityType}[0]->id ?? null;
            if(empty($createdEntityID)) throw new RuntimeException("Unable to create $entityType. Amo response: ".json_encode($response));
            $entity->setId($createdEntityID);
        } catch (\Throwable $e){
            $er = "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}, upd structure: ".json_encode($entity->getUpdateStructureV4());
            throw new RuntimeException($er);
        }
    }

    public static function updateEntity(AmoEntity $entity): void
    {
        try{
            $entityType = $entity->getEntityType();
            if(empty($entity->getId())) throw new InvalidArgumentException("Trying to update not existed entity $entityType");
            $response = self::amo()->__request('patch', "/api/v4/$entityType", [$entity->getUpdateStructureV4()]);
        } catch (\Throwable $e){
            $er = "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}, upd structure: ".json_encode($entity->getUpdateStructureV4());
            throw new RuntimeException($er);
        }
    }

    /**
     * @param array $leadsIds
     * @return Generator<AmoLead>
     */
    public static function getLeadsById(array $leadsIds) : Generator
    {
        return self::getEntitiesById($leadsIds, self::ENTITY_TYPE_LEAD);
    }

    /**
     * @param array $contactsIds
     * @return Generator<AmoContact>>
     */
    public static function getContactsById(array $contactsIds) : Generator
    {
        return self::getEntitiesById($contactsIds, self::ENTITY_TYPE_CONTACT);
    }

    public static function getAllCFs(): ?stdClass
    {
        return self::amo()->getCF();
    }

    public static function issetUser(?int $id): bool
    {
        if(empty($id)) return false;
        $user = self::getAccountUsers()->get($id);
        if(empty($user)) return false;
        return $user->rights->is_active ?? false;
    }
    public static function getAccountUsers(): Collection
    {
        return self::$accountUsers ??
            self::$accountUsers = collect(self::amo()->__request('get','/api/v4/users')->_embedded->users ?? [])
                ->mapWithKeys(function(stdClass $user){return [$user->id => $user];});
    }
    public static function autoUpdateMultiselect($field_id, $value)
    {
        $enum = self::getMultiSelectEnum($field_id, $value);
        if ($enum === false){
            //nofield
            return null;
        }
        if (!$enum) {
            self::updateMultiSelect($field_id, $value);
            $enum = self::getMultiSelectEnum($field_id, $value);
        }
        if ($enum) return $enum;
        return null;
    }

    private static function getMultiSelectEnum($field_f_id, $name) {
        $rez = self::amo()->__request('get', '/api/v2/account', ['with' => 'custom_fields']);
        $reasonsArr = [];
        foreach ($rez->_embedded->custom_fields->leads as $key => $field) {
            if ($key == $field_f_id) {
                $field_name = $field->name;
                foreach ($field->enums as $key => $enum) {
                    $checkEnum = htmlspecialchars_decode($enum);
                    $checkName = $name;
                    if (mb_strtolower(htmlspecialchars_decode($checkEnum)) === mb_strtolower($checkName)) {
                        return $key;
                    }
                }
                return null;
            }
        }
        return false;
    }

    private static function updateMultiSelect($field_f_id, $new_name) {
        $rez = self::amo()->__request('get', '/api/v2/account', ['with' => 'custom_fields']);
        $reasonsArr = [];
        foreach ($rez->_embedded->custom_fields->leads as $key => $field) {
            if ($key == $field_f_id) {
                $field_name = $field->name;
                foreach ($field->enums as $key => $enum) {
                    $old_enums[] = [
                        'id' => (int) $key,
                        'value' => htmlspecialchars_decode($enum)
                    ];
                }
            }
        }

        if (count($old_enums) > 0) {
            $old_enums[] = [
                'value' => $new_name
            ];

            $field = [[
                'id' => $field_f_id,
                'name' => $field_name,
                'enums' => $old_enums
            ]];
            $res = self::amo()->__request('patch', '/api/v4/leads/custom_fields', $field);
        }
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех лидов на аккаунте.
     * context представляет собой массив содержащий параметры запросов. get к амо
     * например filter или with
     *
     * @param array $context
     * @return void
     */
    public static function getLeads(array $context = []): Generator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_LEAD);
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех компаний на аккаунте.
     * context представляет собой массив содержащий параметры запросов. get к амо
     * например filter или with
     *
     * @param array $context
     * @return void
     */
    public static function getCompanies(array $context = []): Generator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_COMPANY);
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех контактов на аккаунте.
     * context представляет собой массив содержащий параметры запросов. get к амо
     * например filter или with
     *
     * @param array $context
     * @return void
     */
    public static function getContacts(array $context = []): Generator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_CONTACT);
    }

    /**
     * Возвращает генератор stdClass объектов которые представляют собой сущность в ответе из амо апи
     *
     * @param array $context
     * @param string $entityType
     * @return Generator
     */
    private static function getAllEntities(array $context, string $entityType): Generator
    {
        $page = 1;
        do{
            $context['page'] = $page++;
            $context['limit'] = $context['limit'] ?? self::ENTITIES_QUERY_LIMIT;
            $amoResponse = self::amo()->__request('get', "/api/v4/$entityType", $context);

            foreach($amoResponse->_embedded->$entityType ?? [] as $entity){
                yield $entity;
            }

        } while($amoResponse->_links->next->href ?? false);
    }


    /**
     * Принимает коллекцию объектов AmoEntity. Все объекты у которых не установлено id будут созданы
     * и для них будет получен id созданной сущности в амо. Все объекты у которых установлено id будут
     * обновлены в амо. Все сущности обрабатываются пакетно, на каждые 50 сущностей по 1 запросу к амо.
     * может принимать смешанную коллекцию содержащую и AmoLead и AmoContact и AmoCompany
     *
     * @param Collection<AmoEntity> $entitiesToSave
     * @return void
     */
    public static function saveEntities(Collection $entitiesToSave): void
    {
        $toUpdate = $entitiesToSave->filter(function(AmoEntity $entity){ return $entity->getId(); });
        $toCreate = $entitiesToSave->filter(function(AmoEntity $entity){ return !$entity->getId(); });
        $allTypesOfEntities = [
            'patch' => [
                self::ENTITY_TYPE_LEAD => $toUpdate->filter(function(AmoEntity $entity){ return $entity instanceof AmoLead; }),
                self::ENTITY_TYPE_CONTACT => $toUpdate->filter(function(AmoEntity $entity){ return $entity instanceof AmoContact; }),
                self::ENTITY_TYPE_COMPANY => $toUpdate->filter(function(AmoEntity $entity){ return $entity instanceof AmoCompany; })
            ],
            'post' => [
                self::ENTITY_TYPE_LEAD => $toCreate->filter(function(AmoEntity $entity){ return $entity instanceof AmoLead; }),
                self::ENTITY_TYPE_CONTACT => $toCreate->filter(function(AmoEntity $entity){ return $entity instanceof AmoContact; }),
                self::ENTITY_TYPE_COMPANY => $toCreate->filter(function(AmoEntity $entity){ return $entity instanceof AmoCompany; })
            ]
        ];

        foreach($allTypesOfEntities as $method => $entityTypes){
            foreach($entityTypes as $type => $entities){
                if($entities->isEmpty()) continue;
                dump($entities);
                $entities = $entities->mapWithKeys(function(AmoEntity $entity){return [$entity->getUniqId() => $entity];});
                dump($entities);
                $requestData = $entities->map(function(AmoEntity $entity){ return $entity->getUpdateStructureV4(); });
                $requestData->chunk(self::ENTITIES_QUERY_DEFAULT)->each(function(Collection $chunk) use ($method, $entities, $type){
                    $response = self::amo()->__request($method, "/api/v4/$type", $chunk->all());
                    dump($response);
                    foreach ($response->_embedded->$type ?? [] as $createdEntity){
                        if($entity = $entities->get($createdEntity->request_id)) $entity->setId($createdEntity->id);
                    }
                });
            }
        }
    }

}
