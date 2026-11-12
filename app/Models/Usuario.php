<?php
declare(strict_types=1);

final class Usuario
{
    public function buscarPorEmail(string $email): ?array
    {
        $sql = 'SELECT u.id, u.nome, u.email, u.senha, u.ativo,
                       GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ",") AS perfis
                FROM usuarios u
                LEFT JOIN usuarios_perfis up ON up.usuario_id = u.id
                LEFT JOIN perfis p ON p.id = up.perfil_id
                WHERE u.email = :email
                GROUP BY u.id';
        $consulta = Database::conexao()->prepare($sql);
        $consulta->execute(['email' => mb_strtolower(trim($email))]);
        $usuario = $consulta->fetch();
        return $usuario ?: null;
    }

    public function perfisDoUsuario(int $usuarioId): array
    {
        $consulta = Database::conexao()->prepare(
            'SELECT p.nome FROM usuarios_perfis up JOIN perfis p ON p.id=up.perfil_id WHERE up.usuario_id=:id ORDER BY p.nome'
        );
        $consulta->execute(['id' => $usuarioId]);
        return array_values(array_map('strval', $consulta->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function ativarPerfilProfissional(int $usuarioId): void
    {
        $pdo = Database::conexao();
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare('SELECT id FROM perfis WHERE nome="profissional" LIMIT 1');
            $s->execute();
            $perfilId = $s->fetchColumn();
            if (!$perfilId) {
                throw new RuntimeException('Perfil profissional indisponível.');
            }
            $vinculo = $pdo->prepare('INSERT IGNORE INTO usuarios_perfis (usuario_id,perfil_id) VALUES (:usuario,:perfil)');
            $vinculo->execute(['usuario' => $usuarioId, 'perfil' => $perfilId]);
            if ($vinculo->rowCount() > 0) {
                $pdo->prepare('INSERT IGNORE INTO configuracoes_profissional (profissional_id) VALUES (:id)')->execute(['id' => $usuarioId]);
                $pdo->prepare('INSERT INTO avisos_perfil (usuario_id,tipo) VALUES (:id,"profissional")')->execute(['id' => $usuarioId]);
            }
            $pdo->commit();
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }
    }

    public function consumirAvisosPerfil(int $usuarioId): array
    {
        $pdo = Database::conexao();
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare(
                'SELECT a.id,a.tipo,p.nome AS profissional_nome
                 FROM avisos_perfil a LEFT JOIN usuarios p ON p.id=a.profissional_id
                 WHERE a.usuario_id=:usuario AND a.lido_em IS NULL
                 ORDER BY a.id LIMIT 20 FOR UPDATE'
            );
            $s->execute(['usuario' => $usuarioId]);
            $avisos = $s->fetchAll(PDO::FETCH_ASSOC);
            if ($avisos) {
                $ids = array_map(static fn(array $aviso): int => (int)$aviso['id'], $avisos);
                $marcadores = implode(',', array_fill(0, count($ids), '?'));
                $atualizar = $pdo->prepare('UPDATE avisos_perfil SET lido_em=NOW() WHERE usuario_id=? AND lido_em IS NULL AND id IN ('.$marcadores.')');
                $atualizar->execute(array_merge([$usuarioId], $ids));
            }
            $pdo->commit();
            return $avisos;
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }
    }

    public function cadastrar(string $nome, string $email, string $senha, array $perfis): int
    {
        $pdo = Database::conexao();
        $pdo->beginTransaction();
        try {
            $consulta = $pdo->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)');
            $consulta->execute([
                'nome' => trim($nome),
                'email' => mb_strtolower(trim($email)),
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);
            $id = (int) $pdo->lastInsertId();
            $perfil = $pdo->prepare('SELECT id FROM perfis WHERE nome = :nome');
            $vincular = $pdo->prepare('INSERT INTO usuarios_perfis (usuario_id, perfil_id) VALUES (:usuario_id, :perfil_id)');
            foreach (array_unique($perfis) as $nomePerfil) {
                $perfil->execute(['nome' => $nomePerfil]);
                $linha = $perfil->fetch();
                if (!$linha) {
                    throw new RuntimeException('Perfil inválido.');
                }
                $vincular->execute(['usuario_id' => $id, 'perfil_id' => $linha['id']]);
            }
            if (in_array('cliente', $perfis, true)) {
                $tabelaClientes = $pdo->query("SHOW TABLES LIKE 'clientes'")->fetchColumn();
                if ($tabelaClientes) {
                    $ligarCliente = $pdo->prepare('UPDATE clientes SET usuario_id=:usuario, atualizado_em=NOW() WHERE email=:email AND usuario_id IS NULL');
                    $ligarCliente->execute(['usuario'=>$id,'email'=>mb_strtolower(trim($email))]);
                }
            }
            $pdo->commit();
            return $id;
        } catch (Throwable $erro) {
            $pdo->rollBack();
            throw $erro;
        }
    }
}
