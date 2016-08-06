<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'TarefaLV', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   TarefaLV
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityoverview'] = 'You have TarefaLVs that need attention';
$string['allowdeleting'] = 'Permitir cancelamento';
$string['allowdeleting_help'] = 'If enabled, students may delete uploaded files at any time before submitting for grading.';
$string['allowmaxfiles'] = 'Número máximo de arquivos carregados';
$string['allowmaxfiles_help'] = 'The maximum number of files which may be uploaded. As this figure is not displayed anywhere, it is suggested that it is mentioned in the TarefaLV description.';
$string['allownotes'] = 'Permitir notas';
$string['allownotes_help'] = 'If enabled, students may enter notes into a text area, as in an online text TarefaLV.';
$string['allowresubmit'] = 'Allow resubmitting';
$string['allowresubmit_help'] = 'If enabled, students will be allowed to resubmit TarefaLVs after they have been graded (for them to be re-graded).';
$string['alreadygraded'] = 'A sua tarefa já foi avaliada. Não é possível enviar outros documentos.';
$string['tarefalv:addinstance'] = 'Add a new TarefaLV';
$string['tarefalvdetails'] = 'Detalhes da tarefa';
$string['tarefalv:exportownsubmission'] = 'Export own submission';
$string['tarefalv:exportsubmission'] = 'Export submission';
$string['tarefalv:grade'] = 'Avaliar tarefa';
$string['tarefalvmail'] = '{$a->teacher} escreveu comentários sobre a seguinte tarefa que você apresentou: \'{$a->tarefalv}\'

Leia os comentários anexos à tarefa:

{$a->url}';
$string['tarefalvmailhtml'] = '{$a->teacher} escreveu comentários sobre a seguinte tarefa que você apresentou: \'<i>{$a->tarefalv}</i>\'<br /><br />
Leia os <a href=\"{$a->url}\">comentários anexos à tarefa</a>.';
$string['tarefalvmailsmall'] = '{$a->teacher} has posted some feedback on your
tarefalv submission for \'{$a->tarefalv}\' You can see it appended to your submission';
$string['tarefalvname'] = 'Nome da tarefa';
$string['tarefalv:submit'] = 'Enviar tarefa';
$string['tarefalvsubmission'] = 'TarefaLV submissions';
$string['tarefalvtype'] = 'Tipo de tarefa';
$string['tarefalv:view'] = 'Ver tarefa';
$string['availabledate'] = 'Disponível a partir de';
$string['cannotdeletefiles'] = 'Erro: os arquivos não foram apagados';
$string['cannotviewtarefalv'] = 'You can not view this tarefalv';
$string['changegradewarning'] = 'This TarefaLV has graded submissions and changing the grade will not automatically re-calculate existing submission grades. You must re-grade all existing submissions, if you wish to change the grade.';
$string['closedtarefalv'] = 'This TarefaLV is closed, as the submission deadline has passed.';
$string['comment'] = 'Comentário';
$string['commentinline'] = 'Comentário inserido na frase';
$string['commentinline_help'] = 'If enabled, the submission text will be copied into the feedback comment field during grading, making it easier to comment inline (using a different colour, perhaps) or to edit the original text.';
$string['configitemstocount'] = 'Tipo de elemento a ser considerado como envio em tarefas online.';
$string['configmaxbytes'] = 'Maior tamanho definido para todas as tarefas do site (sujeita aos limites do curso e às configurações locais).';
$string['configshowrecentsubmissions'] = 'Todos podem ver listas de novos envios no relatório de atividades recentes';
$string['confirmdeletefile'] = 'Tem certeza que quer cancelar este arquivo?<br /><strong>{$a}</strong>';
$string['coursemisconf'] = 'Course is misconfigured';
$string['currentgrade'] = 'Current grade in gradebook';
$string['deleteallsubmissions'] = 'Excluir todos os arquivos enviados';
$string['deletefilefailed'] = 'Não foi cancelado o arquivo';
$string['description'] = 'Descrição';
$string['downloadall'] = 'Download all TarefaLVs as a zip';
$string['draft'] = 'Esboço';
$string['due'] = 'TarefaLV due';
$string['duedate'] = 'Data de entrega';
$string['duedateno'] = 'Nenhuma data de entrega';
$string['early'] = '{$a} antecipado';
$string['editmysubmission'] = 'Editar o documento enviado';
$string['editthesefiles'] = 'Edit these files';
$string['editthisfile'] = 'Update this file';
$string['addsubmission'] = 'Add submission';
$string['emailstudents'] = 'Avisos por email para cursistas';
$string['emailteachermail'] = '{$a->username} atualizou a sua tarefa \'{$a->tarefalv}\'

Para acessar a nova versão:

{$a->url}';
$string['emailteachermailhtml'] = '{$a->username} atualizou a sua tarefa <i>\'{$a->tarefalv}\'</i><br /><br />
Esta pode ser acessada <a href=\"{$a->url}\">no site web</a>.';
$string['emailteachers'] = 'Avisos por email aos professores';
$string['emailteachers_help'] = 'If enabled, teachers receive email notification whenever students add or update an TarefaLV submission.

Only teachers who are able to grade the particular TarefaLV are notified. So, for example, if the course uses separate groups, teachers restricted to particular groups won\'t receive notification about students in other groups.';
$string['emptysubmission'] = 'Você ainda não enviou nada';
$string['enablenotification'] = 'Send notifications';
$string['enablenotification_help'] = 'If enabled, students will be notified when their TarefaLV submissions are graded.';
$string['errornosubmissions'] = 'There are no submissions to download';
$string['existingfiledeleted'] = 'Este arquivo foi cancelado: {$a}';
$string['failedupdatefeedback'] = 'Falhou a atualização do feedback da tarefa do usuário {$a}';
$string['feedback'] = 'Feedback';
$string['feedbackfromteacher'] = 'Feedback de {$a}';
$string['feedbackupdated'] = 'Feedback das tarefas de {$a} pessoas atualizado';
$string['finalize'] = 'Nenhum outro envio';
$string['finalizeerror'] = 'Erro: este envio não foi completado';
$string['futureatarefalv'] = 'This TarefaLV is not yet available.';
$string['graded'] = 'Avaliado';
$string['guestnosubmit'] = 'Sinto muito, visitantes não podem enviar tarefas. Faça o login ou increva-se antes de responder.';
$string['guestnoupload'] = 'Visitantes não podem enviar documentos';
$string['helpoffline'] = '<p>Isto é útil quando a tarefa é realizada fora do Moodle, em outro endereço web ou em presença.</p><p>Os estudante podem ler uma descrição da tarefa, mas não podem enviar documentos. A avaliação das tarefas e a notificação dos estudantes é sempre ativa e pode ser utilizada.</p>';
$string['helponline'] = '<p>Este tipo de tarefa prevê o uso do editor de textos para escrever diretamente no Moodle. Os professores podem avaliar as tarefas, adicionar comentários ou efetuar mudanças.</p>
<p>(este tipo de tarefa substitui o módulo Diário das versões anteriores de Moodle.)</p>';
$string['helpupload'] = '<p>This type of TarefaLV allows each participant to upload one or more files in any format.
   These might be a Word processor documents, images, a zipped web site, or anything you ask them to submit.</p>
   <p>This type also allows you to upload multiple response files. Response files can be also uploaded before submission which
   can be used to give each participant different file to work with.</p>
   <p>Participants may also enter notes describing the submitted files, progress status or any other text information.</p>
   <p>Submission of this type of TarefaLV must be manually finalised by the participant. You can review the current status
   at any time, unfinished TarefaLVs are marked as Draft. You can revert any ungraded TarefaLV back to draft status.</p>';
$string['helpuploadsingle'] = '<p>Este tipo de tarefa prevê que cada estudante envie um documento ao servidor, no formato que for desejado, como Word, imagens, coleções de documentos em arquivo zip, etc.</p>';
$string['hideintro'] = 'Esconder descrição antes da data de abertura';
$string['hideintro_help'] = 'If enabled, the TarefaLV description is hidden before the "Available from" date. Only the TarefaLV name is displayed.';
$string['invalidtarefalv'] = 'Invalid TarefaLV';
$string['invalidfileandsubmissionid'] = 'Missing file or submission ID';
$string['invalidid'] = 'Invalid TarefaLV ID';
$string['invalidsubmissionid'] = 'Invalid submission ID';
$string['invalidtype'] = 'Invalid TarefaLV type';
$string['invaliduserid'] = 'Invalid user ID';
$string['itemstocount'] = 'Contar';
$string['lastgrade'] = 'Last grade';
$string['late'] = '{$a} atrasado';
$string['maximumgrade'] = 'Nota máxima';
$string['maximumsize'] = 'Tamanho máximo';
$string['maxpublishstate'] = 'Maximum visibility for blog entry before due date';
$string['messageprovider:tarefalv_updates'] = 'TarefaLV (2.2) notifications';
$string['modulename'] = 'Tarefa';
$string['modulename_help'] = 'TarefaLVs enable the teacher to specify a task either on or offline which can then be graded.';
$string['modulenameplural'] = 'Tarefas';
$string['newsubmissions'] = 'Tarefas apresentadas';
$string['notarefalvs'] = 'Ainda não há nenhuma tarefa';
$string['noattempts'] = 'Nenhuma tentativa nesta tarefa';
$string['noblogs'] = 'You have no blog entries to submit!';
$string['nofiles'] = 'Nenhum arquivo enviado';
$string['nofilesyet'] = 'Nenhum arquivo enviado ainda';
$string['nomoresubmissions'] = 'Não é possível enviar outros documentos.';
$string['notavailableyet'] = 'Esta tarefa ainda não pode ser acessada..<br /> As instruções serão disponíveis aqui a partir da seguinte data:';
$string['notes'] = 'Notas';
$string['notesempty'] = 'Nenhum item';
$string['notesupdateerror'] = 'Erro durante a atualização das notas';
$string['notgradedyet'] = 'Ainda não avaliada';
$string['norequiregrading'] = 'There are no tarefalvs that require grading';
$string['nosubmisson'] = 'No tarefalvs have been submit';
$string['notsubmittedyet'] = 'Ainda não apresentadas';
$string['oncetarefalvsent'] = 'Depois de enviar a tarefa para avaliação não será possível excluir ou anexar documentos. Deseja continuar?';
$string['operation'] = 'Operation';
$string['optionalsettings'] = 'Optional settings';
$string['overwritewarning'] = 'Atenção: a nova transferência de arquivo vai SUBSTITUIR a tarefa arquivada atualmente';
$string['page-mod-tarefalv-x'] = 'Any TarefaLV module page';
$string['page-mod-tarefalv-view'] = 'TarefaLV module main page';
$string['page-mod-tarefalv-submissions'] = 'TarefaLV module submission page';
$string['pagesize'] = 'Envios mostrados por página';
$string['popupinnewwindow'] = 'Open in a popup window';
$string['pluginadministration'] = 'TarefaLV administration';
$string['pluginname'] = 'TarefaLV';
$string['preventlate'] = 'Impedir envio atrasado';
$string['quickgrade'] = 'Permitir avaliação veloz';
$string['quickgrade_help'] = 'If enabled, multiple TarefaLVs can be graded on one page. Add grades and comments then click the "Save all my feedback" button to save all changes for that page.';
$string['requiregrading'] = 'Require grading';
$string['responsefiles'] = 'Arquivos de resposta';
$string['reviewed'] = 'Revisado';
$string['saveallfeedback'] = 'Gravar notas e comentários';
$string['selectblog'] = 'Select which blog entry you wish to submit';
$string['sendformarking'] = 'Enviar para avaliação';
$string['showrecentsubmissions'] = 'Mostrar envios recentes';
$string['submission'] = 'Envio de tarefas';
$string['submissiondraft'] = 'Esboço do documento';
$string['submissionfeedback'] = 'Feedback';
$string['submissions'] = 'Tarefas enviadas';
$string['submissionsaved'] = 'As suas mudanças foram efetuadas';
$string['submissionsnotgraded'] = '{$a} envios não avaliados';
$string['submittarefalv'] = 'Envie a sua tarefa usando este formulário';
$string['submitedformarking'] = 'A tarefa já foi enviada para avaliação e não pode ser atualizada';
$string['submitformarking'] = 'Enviar tarefa para avaliação';
$string['submitted'] = 'Enviada';
$string['submittedfiles'] = 'Arquivos enviados';
$string['subplugintype_tarefalv'] = 'TarefaLV type';
$string['subplugintype_tarefalv_plural'] = 'TarefaLV types';
$string['trackdrafts'] = 'Habilitar Envio para Avaliação';
$string['trackdrafts_help'] = 'The "Send for marking" button allows students to indicate to the teacher that they have finished working on an TarefaLV. The teacher may choose to revert the TarefaLV to draft status (if it requires further work, for example).';
$string['typeblog'] = 'Blog post';
$string['typeoffline'] = 'Atividade offline';
$string['typeonline'] = 'Texto online';
$string['typeupload'] = 'Modalidade avançada de carregamento de arquivos';
$string['typeuploadsingle'] = 'Envio de  arquivo único';
$string['unfinalize'] = 'Reverter a esboço';
$string['unfinalize_help'] = 'Reverting to draft enables the student to make further updates to their TarefaLV';
$string['unfinalizeerror'] = 'Erro: não foi possível reverter a esboço';
$string['upgradenotification'] = 'This activity is based on an older TarefaLV module.';
$string['uploadafile'] = 'Upload a file';
$string['uploadfiles'] = 'Upload files';
$string['uploadbadname'] = 'O nome deste arquivo contém caracteres estranhos e não pode ser enviado';
$string['uploadedfiles'] = 'Arquivos enviados';
$string['uploaderror'] = 'Erro durante a gravação do arquivo no servidor';
$string['uploadfailnoupdate'] = 'O arquivo foi recebido, mas não foi possível atualizar a sua tarefa!';
$string['uploadfiletoobig'] = 'Infelizmente este arquivo é muito grande (o limite é de {$a} bytes)';
$string['uploadnofilefound'] = 'Não foi encontrado nenhum arquivo - você tem certeza que selecionou um arquivo para enviar?';
$string['uploadnotregistered'] = '\'{$a}\' foi recebido corretamente mas o envio não foi registrado!';
$string['uploadsuccess'] = '\'{$a}\' enviado com sucesso!';
$string['usermisconf'] = 'User is misconfigured';
$string['usernosubmit'] = 'Sorry, you are not allowed to submit an TarefaLV.';
$string['viewtarefalvupgradetool'] = 'View the TarefaLV upgrade tool';
$string['viewfeedback'] = 'Ver avaliação e feedback';
$string['viewmysubmission'] = 'View my submission';
$string['viewsubmissions'] = 'Ver {$a} tarefas enviadas';
$string['yoursubmission'] = 'As suas tarefas';


// @LVS traduções LVs tarefalv

$string['modulename'] = 'Tarefas LV';
$string['modulenameplural'] = 'Tarefas LV';
$string['scalelvs'] = 'Learning Vectors';
$string['avaliacaoatual'] = 'Avaliação Atual';
$string['etapa'] = 'Etapa';
$string['nmensagens'] = 'Número de Mensagens';
$string['postagem'] = 'ª Postagem';
