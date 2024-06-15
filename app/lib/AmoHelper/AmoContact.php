<?php

namespace App\lib\AmoHelper;

use App\lib\AmoMapping\samolet\CF_CONTACT;
use App\lib\AmoMapping\samolet\PIPELINE;
use App\lib\BusinessLogic\Interest;
use App\lib\BusinessLogic\JK;
use App\lib\BusinessLogic\Podbor;
use App\lib\FORMAT;
use stdClass;

class AmoContact extends AmoEntity
{
    protected ?string $firstName;
    protected ?string $lastName;
    protected ?bool $isUnsorted;
    /**  @var AmoLead[] */
    protected array $linkedLeads;
    protected string $entityType = AmoHelper::ENTITY_TYPE_CONTACT;

    public function __construct(?stdClass $contact = null)
    {
        parent::__construct($contact);
        $contact = $contact ?? new stdClass();

        $this->firstName = $contact->first_name ?? null;
        $this->lastName = $contact->last_name ?? null;
        $this->isUnsorted = (bool)($contact->is_unsorted ?? false);
    }

    /**
     * Возвращает массив int номеров телефона контакта
     *
     * @return int[]
     */
    public function getPhones(): array
    {
        return array_map(function($phone){ return (int)$phone; }, $this->getCFV('PHONE'));
    }

    public function setChildrenExistence(bool $exist): self
    {
        $this->setCF(CF_CONTACT::CHILDREN_EXISTS, $exist);
        return $this;
    }

    public function getMainPhone(): int
    {
        return current($this->getPhones());
    }

    /**
     * Возвращает имя контакта, как комбинацию Фамилия + Имя, либо как просто Имя контакта (отдельное поле)
     * Форматирует как имя
     *
     * @return string
     */
    public function getFullName(): string
    {
        return FORMAT::name(implode(' ', [$this->getLastName(), $this->getFirstName()]) ?: $this->getName());
    }

    /**
     * Возвращает массив всех email контакта
     *
     * @return string[]
     */
    public function getEmails(): array
    {
        return $this->getCFV('EMAIL') ?? [];
    }

    public function empty(): bool
    {
        //todo возможно здесь пригодится дополнительная проверка мол, нет телефона и тд например:
        // return parent::empty() && (bool)$this->getPhone();
        return parent::empty();
    }
    public function getFirstName(): ?string { return $this->firstName; }
    public function getLastName(): ?string { return $this->lastName; }
    public function getIsUnsorted(): bool { return $this->isUnsorted; }

    /**
     * Дополняет родительский метод получения структуры для обновления уникальными для контакта полями
     *
     * @return array
     */
    public function getUpdateStructureV4(): array
    {
        $structure = parent::getUpdateStructureV4();
        return array_filter(array_merge($structure, [
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
        ]), function($field){ return !empty($field); });
    }

    /**
     * Получает интерес привязанный к контакту, который не находится в статусах "закрыто"
     *
     * @return Podbor|null
     */
    public function getActivePodbor(): ?Podbor
    {
        return array_filter($this->getPodbors(), function(Podbor $podbor){
            return !in_array($podbor->getStatusId(), [
                PIPELINE::PODBOR_STATUS['SUCCESS'],
                PIPELINE::PODBOR_STATUS['FAILED'],
            ]);
        })[0] ?? null;
    }

    /**
     * Получает интерес привязанный к контакту, который не находится в статусах "закрыто"
     *
     * @return Interest|null
     */
    public function getActiveInterest(): ?Interest
    {
        return array_filter($this->getInterests(), function(Interest $interest){
            return !in_array($interest->getStatusId(), [
                PIPELINE::INTEREST_STATUS['SUCCESS'],
                PIPELINE::INTEREST_STATUS['FAILED'],
            ]);
        })[0] ?? null;
    }

    /**
     * @return JK[]
     */
    public function getJKList(): array
    {
        return array_values(array_map(function(AmoLead $lead){
            return $lead->jk();
        }, array_filter($this->getLinkedLeads(), function($lead){
            return $lead->getPipelineId() === PIPELINE::JK;
        })));
    }

    /**
     * @return Podbor[]
     */
    public function getPodbors(): array
    {
        return array_values(array_map(function(AmoLead $lead){
            return $lead->podbor();
        }, array_filter($this->getLinkedLeads(), function($lead){
            return $lead->getPipelineId() === PIPELINE::PODBOR;
        })));
    }

    /**
     * @return Interest[]
     */
    public function getInterests(): array
    {
        return array_values(array_map(function(AmoLead $lead){
            return $lead->interest();
        }, array_filter($this->getLinkedLeads(), function($lead){
            return $lead->getPipelineId() === PIPELINE::INTEREST;
        })));
    }

    /**
     * @return AmoLead[]
     */
    public function getLinkedLeads(): array
    {
        if(isset($this->linkedLeads)) return $this->linkedLeads;
        return $this->linkedLeads = iterator_to_array(AmoHelper::getLeadsById($this->getLinkedLeadsIds()));
    }

    /**
     * @return int[]
     */
    public function getLinkedLeadsIds(): array
    {
        return array_diff(array_map(function($lead){
            return (int)($lead->id ?? 0) ?: null;
        }, $this->getEmbedded()->leads ?? []), [null]);
    }
}
