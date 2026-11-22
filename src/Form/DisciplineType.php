<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Discipline;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Discipline>
 */
final class DisciplineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'empty_data' => ''])
            ->add('icon', TextType::class, [
                'label' => 'Icône Font Awesome',
                'empty_data' => '',
                'help' => 'Nom de l\'icône sans le préfixe « fa- » (ex. : dumbbell, person-swimming).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Discipline::class]);
    }
}
