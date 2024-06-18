<?php

namespace Mugo\PageBundle\FieldType\MugoPage;


use Ibexa\Contracts\Core\FieldType\Value as SPIValue;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Base\Exceptions\InvalidArgumentType;
use Ibexa\Core\FieldType\FieldType;
use Ibexa\Core\FieldType\ValidationError;
use Ibexa\Core\FieldType\Value as BaseValue;
use Ibexa\Contracts\Core\Repository\Values\Content\Relation;

/**
 * The TextBlock field type.
 *
 * Represents a larger body of text, such as text areas.
 */
class Type extends FieldType
{
    protected $settingsSchema = [
        'textRows' => [
            'type' => 'int',
            'default' => 10,
        ],
    ];

    protected $validatorConfigurationSchema = [];

    /**
     * Returns the field type identifier for this field type.
     *
     * @return string
     */
    public function getFieldTypeIdentifier()
    {
        return 'mugopage';
    }

    /**
     * @param \Mugo\PageBundle\FieldType\MugoPage|\Ibexa\Contracts\Core\FieldType\Value $value
     */
    public function getName(SPIValue $value, FieldDefinition $fieldDefinition, string $languageCode): string
    {
        return (string)$value->text;
    }

    /**
     * Returns the fallback default value of field type when no such default
     * value is provided in the field definition in content types.
     *
     * @return \Mugo\PageBundle\FieldType\MugoPage
     */
    public function getEmptyValue()
    {
        return new Value();
    }

    /**
     * Returns if the given $value is considered empty by the field type.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isEmptyValue(SPIValue $value)
    {
        return $value->text === null || trim($value->text) === '';
    }

    /**
     * Inspects given $inputValue and potentially converts it into a dedicated value object.
     *
     * @param string|\Mugo\PageBundle\FieldType\MugoPage $inputValue
     *
     * @return \Mugo\PageBundle\FieldType\MugoPage The potentially converted and structurally plausible value.
     */
    protected function createValueFromInput($inputValue)
    {
        if (is_string($inputValue)) {
            $inputValue = new Value($inputValue);
        }

        return $inputValue;
    }

    /**
     * Throws an exception if value structure is not of expected format.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException If the value does not match the expected structure.
     *
     * @param \Mugo\PageBundle\FieldType\MugoPage $value
     */
    protected function checkValueStructure(BaseValue $value)
    {
        if (!is_string($value->text)) {
            throw new InvalidArgumentType(
                '$value->text',
                'string',
                $value->text
            );
        }
    }

    /**
     * Returns information for FieldValue->$sortKey relevant to the field type.
     *
     * @param \Mugo\PageBundle\FieldType\MugoPage $value
     *
     * @return string
     */
    protected function getSortInfo(BaseValue $value)
    {
        return false;
    }

    /**
     * Converts an $hash to the Value defined by the field type.
     *
     * @param mixed $hash
     *
     * @return \Mugo\PageBundle\FieldType\MugoPage $value
     */
    public function fromHash($hash)
    {
        if ($hash === null) {
            return $this->getEmptyValue();
        }

        return new Value($hash);
    }

    /**
     * Converts a $Value to a hash.
     *
     * @param \Mugo\PageBundle\FieldType\MugoPage $value
     *
     * @return mixed
     */
    public function toHash(SPIValue $value)
    {
        if ($this->isEmptyValue($value)) {
            return null;
        }

        return $value->text;
    }

    /**
     * Returns whether the field type is searchable.
     *
     * @return bool
     */
    public function isSearchable()
    {
        return false;
    }

    /**
     * Validates the fieldSettings of a FieldDefinitionCreateStruct or FieldDefinitionUpdateStruct.
     *
     * @param mixed $fieldSettings
     *
     * @return \Ibexa\Contracts\Core\FieldType\ValidationError[]
     */
    public function validateFieldSettings($fieldSettings)
    {
        $validationErrors = [];

        foreach ($fieldSettings as $name => $value) {
            if (isset($this->settingsSchema[$name])) {
                switch ($name) {
                    case 'textRows':
                        if (!is_int($value)) {
                            $validationErrors[] = new ValidationError(
                                "Setting '%setting%' value must be of integer type",
                                null,
                                [
                                    '%setting%' => $name,
                                ],
                                "[$name]"
                            );
                        }
                        break;
                }
            } else {
                $validationErrors[] = new ValidationError(
                    "Setting '%setting%' is unknown",
                    null,
                    [
                        '%setting%' => $name,
                    ],
                    "[$name]"
                );
            }
        }

        return $validationErrors;
    }

    /**
     * This overrides the getRelations method
     * This method is called by Ibexa when a content is published
     * Ibexa will store the relations and reverse relations data based on the ids returned here
     * We decode the field value, which is a json string
     * Then we check walk all zones checking each block for related items info
     * @param SPIValue $value
     * @return array
     */
    public function getRelations(SPIValue $value)
    {
        $relatedContentIds = [];
        /* @var \Ibexa\Core\FieldType\RelationList\Value $value */
        $data = @\json_decode($value, true);
        if($data && is_array($data) && isset($data['zones'])) {
            foreach($data['zones'] as $zone ) {

                if(isset($zone['blocks'])) {
                    foreach($zone['blocks'] as $block) {

                        if(isset($block['custom_attributes']) && $block['custom_attributes']){
							foreach ($block['custom_attributes'] as $customAttribute) {

								if ($customAttribute['type'] == 'contentrelation'){
									foreach ($customAttribute['value'] as $relatedContendId){
										$relatedContentIds[] = $relatedContendId;
									}
								}

							}
                        }

                    }
                }

            }
        }

        return [
            Relation::FIELD => $relatedContentIds,
        ];
    }

}