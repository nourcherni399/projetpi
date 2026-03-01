<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226230000_module_quiz_system extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add module_quiz, module_quiz_attempt, module_completion tables for quiz-based module validation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE module_quiz (id INT AUTO_INCREMENT NOT NULL, module_id INT NOT NULL, questions_json JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_MODULE_QUIZ_MODULE (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE module_quiz_attempt (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, module_id INT NOT NULL, quiz_id INT NOT NULL, score_percent DECIMAL(5, 2) NOT NULL, passed TINYINT(1) NOT NULL, answers_json JSON NOT NULL, completed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_QUIZ_ATTEMPT_USER (user_id), INDEX IDX_QUIZ_ATTEMPT_MODULE (module_id), INDEX IDX_QUIZ_ATTEMPT_QUIZ (quiz_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE module_completion (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, module_id INT NOT NULL, quiz_attempt_id INT DEFAULT NULL, completed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_user_module_completion (user_id, module_id), INDEX IDX_MODULE_COMPLETION_USER (user_id), INDEX IDX_MODULE_COMPLETION_MODULE (module_id), INDEX IDX_MODULE_COMPLETION_ATTEMPT (quiz_attempt_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE module_quiz ADD CONSTRAINT FK_MODULE_QUIZ_MODULE FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_quiz_attempt ADD CONSTRAINT FK_QUIZ_ATTEMPT_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_quiz_attempt ADD CONSTRAINT FK_QUIZ_ATTEMPT_MODULE FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_quiz_attempt ADD CONSTRAINT FK_QUIZ_ATTEMPT_QUIZ FOREIGN KEY (quiz_id) REFERENCES module_quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_completion ADD CONSTRAINT FK_MODULE_COMPLETION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_completion ADD CONSTRAINT FK_MODULE_COMPLETION_MODULE FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_completion ADD CONSTRAINT FK_MODULE_COMPLETION_ATTEMPT FOREIGN KEY (quiz_attempt_id) REFERENCES module_quiz_attempt (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module_quiz DROP FOREIGN KEY FK_MODULE_QUIZ_MODULE');
        $this->addSql('ALTER TABLE module_quiz_attempt DROP FOREIGN KEY FK_QUIZ_ATTEMPT_USER');
        $this->addSql('ALTER TABLE module_quiz_attempt DROP FOREIGN KEY FK_QUIZ_ATTEMPT_MODULE');
        $this->addSql('ALTER TABLE module_quiz_attempt DROP FOREIGN KEY FK_QUIZ_ATTEMPT_QUIZ');
        $this->addSql('ALTER TABLE module_completion DROP FOREIGN KEY FK_MODULE_COMPLETION_USER');
        $this->addSql('ALTER TABLE module_completion DROP FOREIGN KEY FK_MODULE_COMPLETION_MODULE');
        $this->addSql('ALTER TABLE module_completion DROP FOREIGN KEY FK_MODULE_COMPLETION_ATTEMPT');
        $this->addSql('DROP TABLE module_quiz');
        $this->addSql('DROP TABLE module_quiz_attempt');
        $this->addSql('DROP TABLE module_completion');
    }
}
