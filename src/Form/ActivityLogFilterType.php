<?php

namespace App\Form;

use App\Entity\ActivityLog;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityLogFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'Action',
                'required' => false,
                'placeholder' => 'All Actions',
                'choices' => [
                    'Login' => ActivityLog::ACTION_LOGIN,
                    'Logout' => ActivityLog::ACTION_LOGOUT,
                    'Create' => ActivityLog::ACTION_CREATE,
                    'Update' => ActivityLog::ACTION_UPDATE,
                    'Delete' => ActivityLog::ACTION_DELETE,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('entityType', ChoiceType::class, [
                'label' => 'Entity Type',
                'required' => false,
                'placeholder' => 'All Entities',
                'choices' => [
                    'User' => ActivityLog::ENTITY_USER,
                    'Room' => ActivityLog::ENTITY_ROOM,
                    'Tour' => ActivityLog::ENTITY_TOUR,
                    'Food' => ActivityLog::ENTITY_FOOD,
                    //'Package' => ActivityLog::ENTITY_PACKAGE,
                    //'Custom Itinerary' => ActivityLog::ENTITY_ITINERARY,
                   // 'Booking' => ActivityLog::ENTITY_BOOKING,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('user', EntityType::class, [
                'label' => 'User',
                'class' => User::class,
                'choice_label' => 'email',
                'required' => false,
                'placeholder' => 'All Users',
                'attr' => ['class' => 'form-select']
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'From Date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'To Date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('search', TextType::class, [
                'label' => 'Search',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Search in description...'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}