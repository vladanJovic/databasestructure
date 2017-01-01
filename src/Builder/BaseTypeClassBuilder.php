<?php

/*
 * This file is part of the Active Collab DatabaseStructure project.
 *
 * (c) A51 doo <info@activecollab.com>. All rights reserved.
 */

namespace ActiveCollab\DatabaseStructure\Builder;

use ActiveCollab\DatabaseConnection\Record\ValueCaster;
use ActiveCollab\DatabaseConnection\Record\ValueCasterInterface;
use ActiveCollab\DatabaseStructure\Association\InjectFieldsInsterface;
use ActiveCollab\DatabaseStructure\AssociationInterface;
use ActiveCollab\DatabaseStructure\Field\Composite\Field as CompositeField;
use ActiveCollab\DatabaseStructure\Field\Scalar\BooleanField;
use ActiveCollab\DatabaseStructure\Field\Scalar\Field as ScalarField;
use ActiveCollab\DatabaseStructure\Field\Scalar\Traits\RequiredInterface;
use ActiveCollab\DatabaseStructure\Field\Scalar\Traits\UniqueInterface;
use ActiveCollab\DatabaseStructure\FieldInterface;
use ActiveCollab\DatabaseStructure\TypeInterface;
use ActiveCollab\DateValue\DateTimeValueInterface;
use ActiveCollab\DateValue\DateValueInterface;
use Doctrine\Common\Inflector\Inflector;

/**
 * @package ActiveCollab\DatabaseStructure\Builder
 */
class BaseTypeClassBuilder extends FileSystemBuilder
{
    /**
     * @param TypeInterface $type
     */
    public function buildType(TypeInterface $type)
    {
        $base_class_name = Inflector::classify(Inflector::singularize($type->getName()));
        $base_class_extends = '\\' . ltrim($type->getBaseClassExtends(), '\\');

        $base_class_build_path = $this->getBuildPath() ? "{$this->getBuildPath()}/Base/$base_class_name.php" : null;

        $result = [];

        $result[] = '<?php';
        $result[] = '';

        if ($this->getStructure()->getConfig('header_comment')) {
            $result = array_merge($result, explode("\n", $this->getStructure()->getConfig('header_comment')));
            $result[] = '';
        }

        $base_class_namespace = $this->getStructure()->getNamespace() ? $this->getStructure()->getNamespace() . '\\Base' : 'Base';

        $result[] = 'namespace ' . $base_class_namespace . ';';
        $result[] = '';
        $result[] = '/**';

        $this->buildBaseClassDocBlockProperties('', $result);

        $result[] = ' * @package ' . $base_class_namespace;
        $result[] = ' */';

        $interfaces = $traits = [];

        foreach ($type->getTraits() as $interface => $implementations) {
            if ($interface != '--just-paste-trait--') {
                $interfaces[] = '\\' . ltrim($interface, '\\');
            }

            if (count($implementations)) {
                foreach ($implementations as $implementation) {
                    $traits[] = '\\' . ltrim($implementation, '\\');
                }
            }
        }

        $result[] = 'abstract class ' . $base_class_name . ' extends ' . $base_class_extends . (empty($interfaces) ? '' : ' implements ' . implode(', ', $interfaces));
        $result[] = '{';

        if (count($traits)) {
            $trait_tweaks_count = count($type->getTraitTweaks());

            $result[] = '    use ' . implode(', ', $traits) . ($trait_tweaks_count ? '{' : ';');

            if ($trait_tweaks_count) {
                for ($i = 0; $i < $trait_tweaks_count - 1; ++$i) {
                    $result[] = '        ' . $type->getTraitTweaks()[$i] . ($i < $trait_tweaks_count - 2 ? ',' : '');
                }

                $result[] = '    }';
            }

            $result[] = '';
        }

        $result[] = '    /**';
        $result[] = '     * Name of the table where records are stored.';
        $result[] = '     *';
        $result[] = '     * @var string';
        $result[] = '     */';
        $result[] = '    protected $table_name = ' . var_export($type->getTableName(), true) . ';';

        $fields = $type->getAllFields();

        $this->buildFields($fields, '    ', $result);
        $this->buildGeneratedFields(array_keys($type->getGeneratedFields()), '    ', $result);

        if (count($type->getProtectedFields())) {
            $result[] = '';
            $result[] = '    /**';
            $result[] = '     * List of protected fields.';
            $result[] = '     *';
            $result[] = '     * @var array';
            $result[] = '     */';
            $result[] = '    protected $protected_fields = [' . implode(', ', array_map(function ($field) {
                return var_export($field, true);
            }, $type->getProtectedFields())) . '];';
        }

        if ($type->getOrderBy() != ['id']) {
            $result[] = '';
            $result[] = '    /**';
            $result[] = '     * @var string[]';
            $result[] = '     */';
            $result[] = '    protected $order_by = [' . implode(', ', array_map(function ($value) {
                return var_export($value, true);
            }, $type->getOrderBy())) . '];';
        }

        $this->buildConfigureMethod($type->getGeneratedFields(), '    ', $result);

        foreach ($type->getAssociations() as $association) {
            $association->buildClassMethods($this->getStructure(), $type, $this->getStructure()->getType($association->getTargetTypeName()), $result);
        }

        foreach ($fields as $field) {
            if ($field instanceof ScalarField && $field->getShouldBeAddedToModel() && $field->getName() != 'id') {
                $this->buildFieldGetterAndSetter($field, '    ', $result);
            }
        }

        foreach ($type->getGeneratedFields() as $field_name => $caster) {
            $this->buildGeneratedFieldGetter($field_name, $caster, '    ', $result);
        }

        $build_custom_get_field_value = false;

        foreach ($fields as $field) {
            if ($field instanceof ScalarField && $field->getShouldBeAddedToModel() && !empty($field->getDeserializingCode('raw_value'))) {
                $build_custom_get_field_value = true;
                break;
            }
        }

        if ($build_custom_get_field_value) {
            $result[] = '';
            $result[] = '    /**';
            $result[] = '     * {@inheritdoc}';
            $result[] = '     */';
            $result[] = '    public function getFieldValue($field, $default = null)';
            $result[] = '    {';
            $result[] = '        $value = parent::getFieldValue($field, $default);';
            $result[] = '';
            $result[] = '        if ($value === null) {';
            $result[] = '            return null;';
            $result[] = '        } else {';
            $result[] = '            switch ($field) {';

            foreach ($fields as $field) {
                if ($field instanceof ScalarField && $field->getShouldBeAddedToModel() && !empty($field->getDeserializingCode('value'))) {
                    $result[] = '                case ' . var_export($field->getName(), true) . ':';
                    $result[] = '                    return ' . $field->getDeserializingCode('value') . ';';
                }
            }

            $result[] = '            }';
            $result[] = '';
            $result[] = '            return $value;';
            $result[] = '        }';
            $result[] = '    }';
        }

        $result[] = '';
        $result[] = '    /**';
        $result[] = '     * {@inheritdoc}';
        $result[] = '     */';
        $result[] = '    public function &setFieldValue($name, $value)';
        $result[] = '    {';
        $result[] = '        if ($value === null) {';
        $result[] = '            parent::setFieldValue($name, null);';
        $result[] = '        } else {';
        $result[] = '            switch ($name) {';

        $casters = [];

        foreach ($fields as $field) {
            if ($field instanceof ScalarField && $field->getShouldBeAddedToModel()) {
                $casting_code = $field->getCastingCode('value');

                if (empty($casters[$casting_code])) {
                    $casters[$casting_code] = [];
                }

                $casters[$casting_code][] = $field->getName();
            }
        }

        foreach ($casters as $caster_code => $casted_field_names) {
            foreach ($casted_field_names as $casted_field_name) {
                $result[] = '                case ' . var_export($casted_field_name, true) . ':';
            }

            $result[] = '                    return parent::setFieldValue($name, ' . $caster_code . ');';
        }

        $result[] = '                default:';
        $result[] = '                    if ($this->isLoading()) {';
        $result[] = '                        return parent::setFieldValue($name, $value);';
        $result[] = '                    } else {';
        $result[] = '                        if ($this->isGeneratedField($name)) {';
        $result[] = '                            throw new \\LogicException("Generated field $name cannot be set by directly assigning a value");';
        $result[] = '                        } else {';
        $result[] = '                            throw new \\InvalidArgumentException("Field $name does not exist in this table");';
        $result[] = '                        }';
        $result[] = '                    }';
        $result[] = '            }';
        $result[] = '        }';
        $result[] = '';
        $result[] = '        return $this;';
        $result[] = '    }';

        $this->buildJsonSerialize($type->getSerialize(), '    ', $result);
        $this->buildCompositeFieldMethods($type->getFields(), '    ', $result);
        $this->buildValidate($type->getFields(), $type->getAssociations(), '    ', $result);

        $result[] = '}';
        $result[] = '';

        $result = implode("\n", $result);

        if ($this->getBuildPath()) {
            file_put_contents($base_class_build_path, $result);
        } else {
            eval(ltrim($result, '<?php'));
        }

        $this->triggerEvent('on_class_built', [$base_class_name, $base_class_build_path]);
    }

    /**
     * @param string $indent
     * @param array  $result
     */
    private function buildBaseClassDocBlockProperties($indent, array &$result)
    {
        $base_class_doc_block_properties = $this->getStructure()->getConfig('base_class_doc_block_properties');

        if (is_array($base_class_doc_block_properties) && !empty($base_class_doc_block_properties)) {
            foreach ($base_class_doc_block_properties as $property => $property_type) {
                $result[] = $indent . " * @property {$property_type} \${$property}";
            }

            $result[] = $indent . ' *';
        }
    }

    /**
     * Build field definitions.
     *
     * @param FieldInterface[] $fields
     * @param string           $indent
     * @param array            $result
     */
    private function buildFields(array $fields, $indent, array &$result)
    {
        $stringified_field_names = [];
        $fields_with_default_value = [];

        foreach ($fields as $field) {
            if ($field instanceof ScalarField && $field->getShouldBeAddedToModel()) {
                $stringified_field_names[] = var_export($field->getName(), true);

                if ($field->getName() != 'id' && $field->getDefaultValue() !== null) {
                    $fields_with_default_value[$field->getName()] = $field->getDefaultValue();
                }
            }
        }

        $result[] = '';
        $result[] = $indent . '/**';
        $result[] = $indent . ' * Table fields that are managed by this entity.';
        $result[] = $indent . ' *';
        $result[] = $indent . ' * @var array';
        $result[] = $indent . ' */';
        $result[] = $indent . 'protected $fields = [' . implode(', ', $stringified_field_names) . '];';

        if (count($fields_with_default_value)) {
            $result[] = '';
            $result[] = $indent . '/**';
            $result[] = $indent . ' * List of default field values.';
            $result[] = $indent . ' *';
            $result[] = $indent . ' * @var array';
            $result[] = $indent . ' */';
            $result[] = $indent . 'protected $default_field_values = [';

            foreach ($fields_with_default_value as $field_name => $default_value) {
                $result[] = $indent . '   ' . var_export($field_name, true) . ' => ' . var_export($default_value, true) . ',';
            }

            $result[] = $indent . '];';
        }
    }

    /**
     * Build a list of generated fields.
     *
     * @param string[] $generated_field_names
     * @param string   $indent
     * @param array    $result
     */
    public function buildGeneratedFields(array $generated_field_names, $indent, array &$result)
    {
        $result[] = '';
        $result[] = $indent . '/**';
        $result[] = $indent . ' * Generated fields that are loaded, but not managed by the entity..';
        $result[] = $indent . ' *';
        $result[] = $indent . ' * @var array';
        $result[] = $indent . ' */';
        $result[] = $indent . 'protected $generated_fields = [' . implode(', ', array_map(function ($field_name) {
            return var_export($field_name, true);
        }, $generated_field_names)) . '];';
    }

    public function buildConfigureMethod(array $generated_fields, $indent, array &$result)
    {
        if (!empty($generated_fields)) {
            $result[] = '';
            $result[] = $indent . '/**';
            $result[] = $indent . ' * {@inheritdoc}';
            $result[] = $indent . ' */';
            $result[] = $indent . 'protected function configure()';
            $result[] = $indent . '{';
            $result[] = $indent . '    $this->setGeneratedFieldsValueCaster(new \\' . ValueCaster::class . '([';

            foreach ($generated_fields as $field_name => $caster) {
                switch ($caster) {
                    case ValueCasterInterface::CAST_INT:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_INT';
                        break;
                    case ValueCasterInterface::CAST_FLOAT:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_FLOAT';
                        break;
                    case ValueCasterInterface::CAST_BOOL:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_BOOL';
                        break;
                    case ValueCasterInterface::CAST_DATE:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_DATE';
                        break;
                    case ValueCasterInterface::CAST_DATETIME:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_DATETIME';
                        break;
                    case ValueCasterInterface::CAST_JSON:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_JSON';
                        break;
                    default:
                        $full_caster = '\\' . ValueCasterInterface::class . '::CAST_STRING';
                }

                $result[] = $indent . '        ' . var_export($field_name, true) . ' => ' . $full_caster . ',';
            }

            $result[] = $indent . '    ]));';
            $result[] = $indent . '}';

//            $this->setGeneratedFieldsValueCaster(new ValueCaster([
//                'is_used_on_day' => ValueCasterInterface::CAST_BOOL,
//                'plan_name' => ValueCasterInterface::CAST_STRING,
//                'number_of_users' => ValueCasterInterface::CAST_INT,
//            ]));
        }
    }

    /**
     * @param FieldInterface[] $fields
     * @param string           $indent
     * @param array            $result
     */
    private function buildCompositeFieldMethods($fields, $indent, array &$result)
    {
        foreach ($fields as $field) {
            if ($field instanceof CompositeField) {
                $field->getBaseClassMethods($indent, $result);
            }
        }
    }

    /**
     * @param ScalarField $field
     * @param string      $indent
     * @param array       $result
     */
    private function buildFieldGetterAndSetter(ScalarField $field, $indent, array &$result)
    {
        $setter_access_level = $field->getProtectSetter() ? 'protected' : 'public';

        $lines = [];

        $short_getter = null;

        $type_for_executable_code = $this->getTypeForExecutableCode($field->getNativeType(), $field->isRequired());
        $type_for_doc_block = $this->getTypeForDocBlock($field->getNativeType(), $field->isRequired());

        if ($field instanceof BooleanField && $this->useShortGetterName($field->getName())) {
            $short_getter = $this->getShortGetterName($field->getName());

            $lines[] = '';
            $lines[] = '/**';
            $lines[] = ' * Return value of ' . $field->getName() . ' field.';
            $lines[] = ' *';
            $lines[] = ' * @return ' . $type_for_doc_block;
            $lines[] = ' */';
            $lines[] = 'public function ' . $short_getter . '()' . ($type_for_executable_code ? ': ' : '') . $type_for_executable_code;
            $lines[] = '{';
            $lines[] = '    return $this->getFieldValue(' . var_export($field->getName(), true) . ');';
            $lines[] = '}';
        }

        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * Return value of ' . $field->getName() . ' field.';
        $lines[] = ' *';
        $lines[] = ' * @return ' . $type_for_doc_block;

        if ($short_getter && $this->getStructure()->getConfig('deprecate_long_bool_field_getter')) {
            $lines[] = " * @deprecated use $short_getter()";
        }

        $lines[] = ' */';
        $lines[] = 'public function ' . $this->getGetterName($field->getName()) . '()' . ($type_for_executable_code ? ': ' : '') . $type_for_executable_code;
        $lines[] = '{';
        $lines[] = '    return $this->getFieldValue(' . var_export($field->getName(), true) . ');';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * Set value of ' . $field->getName() . ' field.';
        $lines[] = ' *';
        $lines[] = ' * @param  ' . str_pad($type_for_doc_block, 5, ' ', STR_PAD_RIGHT) . ' $value';
        $lines[] = ' * @return $this';
        $lines[] = ' */';
        $lines[] = $setter_access_level . ' function &' . $this->getSetterName($field->getName()) . '(' . $type_for_executable_code . ($type_for_executable_code ? ' ' : '') . '$value)';
        $lines[] = '{';
        $lines[] = '    $this->setFieldValue(' . var_export($field->getName(), true) . ', $value);';
        $lines[] = '';
        $lines[] = '    return $this;';
        $lines[] = '}';

        foreach ($lines as $line) {
            $result[] = $line ? $indent . $line : '';
        }
    }

    private function getTypeForExecutableCode(string $native_type, bool $field_is_required): string
    {
        $result = '';

        if ($native_type != 'mixed') {
            $result = ($field_is_required ? '' : '?') . $native_type;
        }

        return $result;
    }

    private function getTypeForDocBlock(string $native_type, bool $field_is_required): string
    {
        if ($native_type === 'mixed') {
            return $native_type;
        }

        return $native_type . ($field_is_required ?  '' : '|null');
    }

    /**
     * Build getter for generated field.
     *
     * @param string $field_name
     * @param string $caster
     * @param string $indent
     * @param array  $result
     */
    private function buildGeneratedFieldGetter($field_name, $caster, $indent, array &$result)
    {
        $short_getter = null;

        switch ($caster) {
            case ValueCasterInterface::CAST_INT:
                $return_type = 'int';
                break;
            case ValueCasterInterface::CAST_FLOAT:
                $return_type = 'float';
                break;
            case ValueCasterInterface::CAST_BOOL:
                $return_type = 'bool';
                break;
            case ValueCasterInterface::CAST_DATE:
                $return_type = '\\' . DateValueInterface::class;
                break;
            case ValueCasterInterface::CAST_DATETIME:
                $return_type = '\\' . DateTimeValueInterface::class;
                break;
            case ValueCasterInterface::CAST_JSON:
                $return_type = 'mixed';
                break;
            default:
                $return_type = 'string';
        }

        if ($this->useShortGetterName($field_name)) {
            $short_getter = $this->getShortGetterName($field_name);

            $lines[] = '';
            $lines[] = '/**';
            $lines[] = ' * Return value of ' . $field_name . ' field.';
            $lines[] = ' *';
            $lines[] = ' * @return ' . $return_type;
            $lines[] = ' */';
            $lines[] = 'public function ' . $short_getter . '()';
            $lines[] = '{';
            $lines[] = '    return $this->getFieldValue(' . var_export($field_name, true) . ');';
            $lines[] = '}';
        }

        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * Return value of ' . $field_name . ' field.';
        $lines[] = ' *';
        $lines[] = ' * @return ' . $return_type;

        if ($short_getter && $this->getStructure()->getConfig('deprecate_long_bool_field_getter')) {
            $lines[] = " * @deprecated use $short_getter()";
        }

        $lines[] = ' */';
        $lines[] = 'public function ' . $this->getGetterName($field_name) . '()';
        $lines[] = '{';
        $lines[] = '    return $this->getFieldValue(' . var_export($field_name, true) . ');';
        $lines[] = '}';
        $lines[] = '';

        foreach ($lines as $line) {
            $result[] = $line ? $indent . $line : '';
        }
    }

    /**
     * Return true if we should use a short getter name.
     *
     * @param  string $field_name
     * @return bool
     */
    private function useShortGetterName($field_name)
    {
        return substr($field_name, 0, 3) === 'is_' || in_array(substr($field_name, 0, 4), ['has_', 'had_', 'was_']) || in_array(substr($field_name, 0, 5), ['were_', 'have_']);
    }

    /**
     * Build JSON serialize method, if we need to serialize extra fields.
     *
     * @param array  $serialize
     * @param string $indent
     * @param array  $result
     */
    private function buildJsonSerialize(array $serialize, $indent, array &$result)
    {
        if (count($serialize)) {
            $result[] = '';
            $result[] = $indent . '/**';
            $result[] = $indent . ' * Prepare object properties so they can be serialized to JSON.';
            $result[] = $indent . ' *';
            $result[] = $indent . ' * @return array';
            $result[] = $indent . ' */';
            $result[] = $indent . 'public function jsonSerialize()';
            $result[] = $indent . '{';
            $result[] = $indent . '    return array_merge(parent::jsonSerialize(), [';

            foreach ($serialize as $field) {
                $result[] = $indent . '        ' . var_export($field, true) . ' => $this->' . $this->getGetterName($field) . '(),';
            }

            $result[] = $indent . '    ]);';
            $result[] = $indent . '}';
        }
    }

    /**
     * @param FieldInterface[]       $fields
     * @param AssociationInterface[] $associations
     * @param string                 $indent
     * @param array                  $result
     */
    private function buildValidate(array $fields, array $associations, $indent, array &$result)
    {
        $fields_to_validate = $fields;

        foreach ($associations as $association) {
            if ($association instanceof InjectFieldsInsterface && count($association->getFields())) {
                $fields_to_validate = array_merge($fields_to_validate, $association->getFields());
            }
        }

        $validator_lines = [];
        $line_indent = $indent  . '    ';

        foreach ($fields_to_validate as $field) {
            if ($field instanceof CompositeField) {
                $field->getValidatorLines($line_indent, $validator_lines);

                foreach ($field->getFields() as $subfield) {
                    if ($subfield instanceof ScalarField && $subfield->getShouldBeAddedToModel()) {
                        $this->buildValidatePresenceLinesForScalarField($subfield, $line_indent, $validator_lines);
                    }
                }
            } elseif ($field instanceof ScalarField && $field->getShouldBeAddedToModel()) {
                $this->buildValidatePresenceLinesForScalarField($field, $line_indent, $validator_lines);
            }
        }

        if (count($validator_lines)) {
            $result[] = '';
            $result[] = $indent . '/**';
            $result[] = $indent . ' * Validate object properties before object is saved.';
            $result[] = $indent . ' *';
            $result[] = $indent . ' * @param \ActiveCollab\DatabaseObject\ValidatorInterface $validator';
            $result[] = $indent . ' */';
            $result[] = $indent . 'public function validate(\ActiveCollab\DatabaseObject\ValidatorInterface &$validator)';
            $result[] = $indent . '{';

            $result = array_merge($result, $validator_lines);

            $result[] = '';
            $result[] = $indent . '    parent::validate($validator);';
            $result[] = $indent . '}';
        }
    }

    /**
     * Build validate lines for scalar fields.
     *
     * @param ScalarField $field
     * @param string      $line_indent
     * @param array       $validator_lines
     */
    private function buildValidatePresenceLinesForScalarField(ScalarField $field, $line_indent, array &$validator_lines)
    {
        if ($field instanceof RequiredInterface && $field instanceof UniqueInterface) {
            if ($field->isRequired() && $field->isUnique()) {
                $validator_lines[] = $line_indent . $this->buildValidatePresenceAndUniquenessLine($field->getName(), $field->getUniquenessContext());
            } elseif ($field->isRequired()) {
                $validator_lines[] = $line_indent . $this->buildValidatePresenceLine($field->getName());
            } elseif ($field->isUnique()) {
                $validator_lines[] = $line_indent . $this->buildValidateUniquenessLine($field->getName(), $field->getUniquenessContext());
            }
        } elseif ($field instanceof RequiredInterface && $field->isRequired()) {
            $validator_lines[] = $line_indent . $this->buildValidatePresenceLine($field->getName());
        } elseif ($field instanceof UniqueInterface && $field->isUnique()) {
            $validator_lines[] = $line_indent . $this->buildValidateUniquenessLine($field->getName(), $field->getUniquenessContext());
        }
    }

    /**
     * Build validator value presence line.
     *
     * @param  string $field_name
     * @return string
     */
    private function buildValidatePresenceLine($field_name)
    {
        return '$validator->present(' . var_export($field_name, true) . ');';
    }

    /**
     * Build validator uniqueness line.
     *
     * @param  string $field_name
     * @param  array  $context
     * @return string
     */
    private function buildValidateUniquenessLine($field_name, array $context)
    {
        $field_names = [var_export($field_name, true)];

        foreach ($context as $v) {
            $field_names[] = var_export($v, true);
        }

        return '$validator->unique(' . implode(', ', $field_names) . ');';
    }

    /**
     * Build validator uniqueness line.
     *
     * @param  string $field_name
     * @param  array  $context
     * @return string
     */
    private function buildValidatePresenceAndUniquenessLine($field_name, array $context)
    {
        $field_names = [var_export($field_name, true)];

        foreach ($context as $v) {
            $field_names[] = var_export($v, true);
        }

        return '$validator->presentAndUnique(' . implode(', ', $field_names) . ');';
    }

    /**
     * @var array
     */
    private $getter_names = [], $setter_names = [];

    /**
     * @param  string $field_name
     * @return string
     */
    private function getGetterName($field_name)
    {
        if (empty($this->getter_names[$field_name])) {
            $camelized_field_name = Inflector::classify($field_name);

            $this->getter_names[$field_name] = "get{$camelized_field_name}";
            $this->setter_names[$field_name] = "set{$camelized_field_name}";
        }

        return $this->getter_names[$field_name];
    }

    /**
     * Return short getter name, without get bit.
     *
     * @param  string $field_name
     * @return string
     */
    private function getShortGetterName($field_name)
    {
        return lcfirst(Inflector::classify($field_name));
    }

    /**
     * @param  string $field_name
     * @return string
     */
    private function getSetterName($field_name)
    {
        if (empty($this->setter_names[$field_name])) {
            $camelized_field_name = Inflector::classify($field_name);

            $this->getter_names[$field_name] = "get{$camelized_field_name}";
            $this->setter_names[$field_name] = "set{$camelized_field_name}";
        }

        return $this->setter_names[$field_name];
    }
}
