<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Discipline;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Discipline>
 */
final class DisciplineType extends AbstractType
{
    /** Languages, besides the default (French) one, for which a name can be entered. */
    private const array TRANSLATED_LOCALES = ['en', 'es'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'form.discipline.name', 'empty_data' => '']);

        foreach (self::TRANSLATED_LOCALES as $locale) {
            $builder->add('name_'.$locale, TextType::class, [
                'label' => 'form.discipline.name_'.$locale,
                'required' => false,
                'help' => 'form.discipline.name_translation_help',
                'getter' => static fn (Discipline $discipline): ?string => $discipline->getNameTranslation($locale),
                'setter' => static function (Discipline $discipline, ?string $name) use ($locale): void {
                    $discipline->setNameTranslation($locale, $name);
                },
                'constraints' => [new Assert\Length(max: 80)],
            ]);
        }

        $builder->add('icon', TextType::class, [
            'label' => 'form.discipline.icon',
            'empty_data' => '',
            'help' => 'form.discipline.icon_help',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Discipline::class]);
    }
}
