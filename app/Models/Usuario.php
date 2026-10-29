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
