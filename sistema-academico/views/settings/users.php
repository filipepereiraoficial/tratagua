<div class="pagina__cabecalho">
  <div>
    <h1>Usuários</h1>
    <p class="mudo mb-0">Perfis: administrador, professor e aluno (o painel do aluno já está previsto na estrutura).</p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/configuracoes') ?>">Voltar às configurações</a></div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<div class="colunas colunas--2-1">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2><?= count($usuarios) ?> usuário(s)</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Usuário</th><th>Perfil</th><th>Ativo</th><th>Último acesso</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($usuarios as $usuario): ?>
            <tr>
              <td colspan="5" style="padding:.7rem">
                <form method="post" action="<?= url('/configuracoes/usuarios/' . $usuario['id']) ?>"
                      style="display:grid;grid-template-columns:1.4fr 1.4fr 1fr .6fr auto;gap:.5rem;align-items:center">
                  <?= csrf_field() ?>
                  <input type="text" name="name" value="<?= e($usuario['name']) ?>" required maxlength="150">
                  <input type="email" name="email" value="<?= e($usuario['email']) ?>" required maxlength="150">
                  <select name="role">
                    <?php foreach (['admin', 'professor', 'aluno'] as $papel): ?>
                      <option value="<?= $papel ?>" <?= $usuario['role'] === $papel ? 'selected' : '' ?>><?= e(rotulo('papel', $papel)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $usuario['is_active'] === 1 ? 'checked' : '' ?>> ativo
                  </label>
                  <button class="botao botao--pequeno botao--secundario" type="submit">Salvar</button>
                </form>
                <div class="pequeno mudo mt-1" style="display:flex;gap:.8rem;align-items:center;flex-wrap:wrap">
                  <span>Último acesso: <?= datahora_br($usuario['last_login_at'], 'nunca') ?></span>
                  <?php if ((int) $usuario['must_change_password'] === 1): ?>
                    <span class="etiqueta etiqueta--medio">deve trocar a senha</span>
                  <?php endif; ?>

                  <form method="post" action="<?= url('/configuracoes/usuarios/' . $usuario['id'] . '/senha') ?>"
                        style="display:flex;gap:.3rem;align-items:center;margin-left:auto">
                    <?= csrf_field() ?>
                    <input type="password" name="password" placeholder="nova senha" minlength="8" style="width:150px">
                    <button class="botao botao--pequeno botao--secundario" type="submit">Redefinir</button>
                  </form>

                  <form method="post" action="<?= url('/configuracoes/usuarios/' . $usuario['id'] . '/excluir') ?>"
                        data-confirmar="Excluir este usuário?">
                    <?= csrf_field() ?>
                    <button class="botao botao--pequeno botao--perigo" type="submit">Excluir</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Novo usuário</h2></div>
    <div class="cartao__corpo">
      <form method="post" action="<?= url('/configuracoes/usuarios') ?>">
        <?= csrf_field() ?>
        <div class="campo mb-2">
          <label for="name">Nome *</label>
          <input type="text" id="name" name="name" required maxlength="150">
        </div>
        <div class="campo mb-2">
          <label for="email">E-mail *</label>
          <input type="email" id="email" name="email" required maxlength="150">
        </div>
        <div class="campo mb-2">
          <label for="password">Senha inicial *</label>
          <input type="password" id="password" name="password" required minlength="8">
          <span class="ajuda">O usuário será obrigado a trocá-la no primeiro acesso.</span>
        </div>
        <div class="campo mb-2">
          <label for="role">Perfil *</label>
          <select id="role" name="role" required>
            <option value="professor">Professor</option>
            <option value="admin">Administrador</option>
            <option value="aluno">Aluno</option>
          </select>
        </div>
        <div class="campo mb-2">
          <label for="student_id">Vincular a um aluno (perfil Aluno)</label>
          <select id="student_id" name="student_id">
            <option value="">— Não vincular —</option>
            <?php foreach ($alunos as $aluno): ?>
              <option value="<?= (int) $aluno['id'] ?>"><?= e($aluno['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="botao botao--bloco" type="submit">Criar usuário</button>
      </form>
    </div>
  </div>
</div>
