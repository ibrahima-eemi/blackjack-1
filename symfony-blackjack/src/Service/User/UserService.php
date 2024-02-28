<?php

namespace App\Service\User;

use App\DTO\Response\Error;
use App\DTO\Response\Success;
use App\DTO\User\CreateUserDTO;
use App\Entity\User;
use App\Form\User\CreateUserType;
use App\Repository\UserRepository;
use App\Service\Form\FormService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;

class UserService
{
    private EntityManagerInterface $entityManager;
    private FormFactoryInterface $formFactory;

    public function __construct(EntityManagerInterface $entityManager, FormFactoryInterface $formFactory)
    {
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
    }

    public function getUserList(): array
    {
        return $this->entityManager->getRepository(User::class)->findAll();
    }

    public function getUserByUuid(string $uuid): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['id' => $uuid]);
    }

    public function createUser(array $payload): Success | Error
    {
        $createUserDTO = $this->createUserValidatePayload($payload);

        if($createUserDTO instanceof Error) {
            return $createUserDTO;
        }

        $user = $this->createUserFromDTO($createUserDTO);

        $this->entityManager->getRepository(User::class)->save($user, true);

        return new Success([$user], 201);
    }

    private function createUserValidatePayload(array $payload): CreateUserDTO | Error
    {
        $createUserDTO = new CreateUserDTO();
        $form = $this->formFactory->create(CreateUserType::class, $createUserDTO);

        $form->submit($payload);

        $errors = [];

        if(!$form->isValid()) {
            $errors = FormService::getFormErrors($form);
        }

        $usernameAlreadyExists = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $createUserDTO->getUsername()]);
        $emailAlreadyExists = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $createUserDTO->getEmail()]);

        if(empty($usernameAlreadyExists) === false) {
            $form->get('username')->addError(new FormError('Username already exists'));
        }

        if(empty($emailAlreadyExists) === false) {
            $form->get('email')->addError(new FormError('Email already exists'));
        }

        $errors = array_merge($errors, FormService::getFormErrors($form));

        if(!empty($errors)) {
            return new Error($errors, 400);
        }

        return $createUserDTO;
    }

    private function createUserFromDTO(CreateUserDTO $createUserDTO): User
    {
        $user = new User();
        $user->setEmail($createUserDTO->getEmail());
        $user->setUsername($createUserDTO->getUsername());
        $user->setPassword($createUserDTO->getPassword());
        $user->setCreationDate(new \DateTime());
        $user->setLastUpdateDate(new \DateTime());

        return $user;
    }

}