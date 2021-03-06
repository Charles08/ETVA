<div class="help">
<a id="help-lvol"><h1>Logical Volumes</h1></a>
<p>As operações disponíveis sobre os <em>logical volumes</em> são as seguintes:</p>

<ul>
    <li><a href="#help-lvol-add">Criar</a></li>
    <li><a href="#help-lvol-rs">Redimensionar</a></li>
    <li><a href="#help-lvol-rm">Remover</a></li>
    <li><a href="#help-lvol-clone">Clonar</a></li>
    <li><a href="#help-lvol-convert">Converter</a></li>
    <li><a href="#help-lvol-scan">Procurar <em>logical volumes</em></a></li>
</ul>

<br/>
<hr/>


<a id="help-lvol-add"><h1>Criar <em><b>Volume Group</b></em></h1></a>
<p>Para criar um <em>logical volume</em> acede-se ao sub-menu de contexto sobre um qualquer <em>logical volume</em> e
selecciona-se <em>Adicionar logical volume</em>.</p>
<p>Na janela de criação deverá ser introduzido o nome pretendido,
o <em>volume group</em> a partir do qual se criará, e o tamanho que não
deverá exceder o tamanho disponível no <em>volume group</em>.
</p>
<p>É ainda possível definir o formato do disco de um dos possíveis (raw, qcow2, qcow, cow e vmdk - por omissão raw) e indicar a percentagem de espaço a ser usada para <em>snapshots</em></p>
<a href="#help-lvol"><div>Início</div></a>
<hr/>

<a id="help-lvol-rs"><h1>Redimensionar <em><b>Logical Volumes</b></em></h1></a>
No redimensionamento selecciona-se o <em>logical volume</em> que se pretende redimensionar e
acede-se ao sub-menu de contexto. Aí existe a opção <em>Redimensionar logical volume</em> que
permite aumentar/reduzir o tamanho do <em>logical volume</em>.

<p><b>Nota:</b> Ao reduzir o tamanho do <em>logical volume</em> poderá tornar os dados existentes inutilizados.
    É da responsabilidade do utilizador verificar se é comportável/seguro o
redimensionamento do <em>logical volume</em> sem afectar os dados nele contidos.</p>
<a href="#help-lvol"><div>Início</div></a>
<hr/>


<a id="help-lvol-rm"><h1>Remover <em><b>Logical Volumes</b></em></h1></a>
<p>Na remoção de um <em>logical volume</em>, no sub-menu de contexto existe a opção <em>Remover logical
volume</em>. O <em>logical volume</em> só será removido se não tiver associado a nenhuma máquina
virtual. Para verificar se está em uso passa-se o rato por cima do <em>logical volume</em> e observar
a informação contida no <em>tooltip</em> que aparece.</p>
<a href="#help-lvol"><div>Início</div></a>
<hr/>

<a id="help-lvol-clone"><h1>Clonar <em><b>Logical Volumes</b></em></h1></a>
<p>Esta opção cria um novo volume com a dimensão do volume que se pretende clonar.</p>
<p>Apenas está disponível caso o <em>logical volume</em> não esteja em uso. Para além desta condição, é também necessário que exista pelo menos um <em>volume group</em> que tenha espaço suficiente para receber o novo volume. 
</p>
<p>
Se o <em>volume group</em> de destino for um volume partilhado, as alterações serão propagadas para os restantes nós do datacenter.
</p>
<a href="#help-lvol"><div>Início</div></a>

<a id="help-lvol-convert"><h1>Converter <em><b>Logical Volumes</b></em></h1></a>
<p>Ao escolher esta opção é possível converter o disco para um dos formatos indicados (raw, qcow2, qcow, cow e vmdk).</p>
<a href="#help-lvol"><div>Início</div></a>

<a id="help-lvol-scan"><h1>Procurar <em><b>Logical Volumes</b></em></h1></a>
<p>Em <em>Procurar logical volumes</em> sincroniza os <em>logical volumes</em> que se encontram do lado do agente de virtualização e não estão registados no <em>Central Management</em>.
É possível também que existam <em>logical volumes</em> que se encontram registados no <em>Central Management</em> mas não existam físicamente por alguma razão alheia ao sistema.
Nestes casos, é possível remover o registo do sistema e voltar a sincronizar os <em>logical volumes</em> com a funcionalidade <em>Procurar logical volumes</em>.</p>
<a href="#help-lvol"><div>Início</div></a>
<hr/>

</div>
