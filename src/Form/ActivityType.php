<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Discipline;
use App\Enum\Level;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Activity>
 */
final class ActivityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Activity $activity */
        $activity = $builder->getData();

        $builder
            ->add('name', TextType::class, ['label' => 'Nom du cours', 'empty_data' => ''])
            ->add('discipline', EntityType::class, [
                'label' => 'Discipline',
                'class' => Discipline::class,
                'choice_label' => 'name',
                'choices' => $activity->getStudio()->getDisciplines(),
                'placeholder' => 'Choisir…',
            ])
            ->add('level', EnumType::class, [
                'label' => 'Niveau',
                'class' => Level::class,
                'choice_label' => static fn (Level $level): string => $level->label(),
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'empty_data' => '0',
                'attr' => ['min' => 15, 'max' => 480, 'step' => 5],
            ])
            ->add('defaultCapacity', IntegerType::class, [
                'label' => 'Places par séance (par défaut)',
                'empty_data' => '0',
                'attr' => ['min' => 1, 'max' => 500],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
            'empty_data' => null,
        ]);
    }
}
