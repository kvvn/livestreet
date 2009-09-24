<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

/**
 * Класс обработки URL'ов вида /blog/
 *
 */
class ActionBlog extends Action {
	/**
	 * Главное меню
	 *
	 * @var unknown_type
	 */
	protected $sMenuHeadItemSelect='blog';
	/**
	 * Какое меню активно
	 *
	 * @var unknown_type
	 */
	protected $sMenuItemSelect='blog';
	/**
	 * Какое подменю активно
	 *
	 * @var unknown_type
	 */
	protected $sMenuSubItemSelect='good';
	/**
	 * УРЛ блога который подставляется в меню
	 *
	 * @var unknown_type
	 */
	protected $sMenuSubBlogUrl;
	/**
	 * Текущий пользователь
	 *
	 * @var unknown_type
	 */
	protected $oUserCurrent=null;
	
	/**
	 * Число новых топиков в коллективных блогах
	 *
	 * @var unknown_type
	 */
	protected $iCountTopicsCollectiveNew=0;
	/**
	 * Число новых топиков в персональных блогах
	 *
	 * @var unknown_type
	 */
	protected $iCountTopicsPersonalNew=0;
	/**
	 * Число новых топиков в конкретном блоге
	 *
	 * @var unknown_type
	 */
	protected $iCountTopicsBlogNew=0;
	/**
	 * Число новых топиков
	 *
	 * @var unknown_type
	 */
	protected $iCountTopicsNew=0;
	/**
	 * Список URL с котрыми запрещено создавать блог
	 *
	 * @var unknown_type
	 */
	protected $aBadBlogUrl=array('new','good','bad','edit','add','admin');
	
	/**
	 * Инизиализация экшена
	 *
	 */
	public function Init() {		
		/**
		 * Устанавливаем евент по дефолту, т.е. будем показывать хорошие топики из коллективных блогов
		 */
		$this->SetDefaultEvent('good');
		$this->sMenuSubBlogUrl=Router::GetPath('blog');
		/**
		 * Достаём текущего пользователя
		 */
		$this->oUserCurrent=$this->User_GetUserCurrent();	
		/**
		 * Определяем какие блоки нужно выводить справа
		 */
		$this->Viewer_AddBlocks('right',array('stream','tags','blogs'));		
		/**
		 * Подсчитываем новые топики
		 */
		$this->iCountTopicsCollectiveNew=$this->Topic_GetCountTopicsCollectiveNew();
		$this->iCountTopicsPersonalNew=$this->Topic_GetCountTopicsPersonalNew();	
		$this->iCountTopicsBlogNew=$this->iCountTopicsCollectiveNew;
		$this->iCountTopicsNew=$this->iCountTopicsCollectiveNew+$this->iCountTopicsPersonalNew;
	}
	
	/**
	 * Регистрируем евенты, по сути определяем УРЛы вида /blog/.../
	 *
	 */
	protected function RegisterEvent() {			
		$this->AddEventPreg('/^good$/i','/^(page(\d+))?$/i','EventTopics');
		$this->AddEvent('good','EventTopics');
		$this->AddEventPreg('/^bad$/i','/^(page(\d+))?$/i','EventTopics');
		$this->AddEventPreg('/^new$/i','/^(page(\d+))?$/i','EventTopics');
		
		$this->AddEvent('add','EventAddBlog');
		$this->AddEvent('edit','EventEditBlog');
		$this->AddEvent('admin','EventAdminBlog');
		$this->AddEvent('invite','EventInviteBlog');
		
		$this->AddEvent('ajaxaddcomment','AjaxAddComment');
		$this->AddEvent('ajaxaddbloginvite', 'AjaxAddBlogInvite');
		
		$this->AddEventPreg('/^(\d+)\.html$/i','/^$/i','EventShowTopic');
		$this->AddEventPreg('/^[\w\-\_]+$/i','/^(\d+)\.html$/i','EventShowTopic');
				
		$this->AddEventPreg('/^[\w\-\_]+$/i','/^(page(\d+))?$/i','EventShowBlog');
		$this->AddEventPreg('/^[\w\-\_]+$/i','/^bad$/i','/^(page(\d+))?$/i','EventShowBlog');
		$this->AddEventPreg('/^[\w\-\_]+$/i','/^new$/i','/^(page(\d+))?$/i','EventShowBlog');			
	}
		
	
	/**********************************************************************************
	 ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
	 **********************************************************************************
	 */	
	/**
	 * Добавление нового блога
	 *
	 * @return unknown
	 */
	protected function EventAddBlog() {
		$this->Viewer_AddHtmlTitle($this->Lang_Get('blog_create'));
		/**
		 * Меню
		 */
		$this->sMenuSubItemSelect='add';
		$this->sMenuItemSelect='add_blog';				
		/**
		 * Проверяем авторизован ли пользователь
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle($this->Lang_Get('not_access'),$this->Lang_Get('error'));
			return Router::Action('error');
		}		
		/**
		 * Проверяем хватает ли рейтинга юзеру чтоб создать блог
		 */
		if (!$this->ACL_CanCreateBlog($this->oUserCurrent) and !$this->oUserCurrent->isAdministrator()) {
			$this->Message_AddErrorSingle($this->Lang_Get('blog_create_acl'),$this->Lang_Get('error'));
			return Router::Action('error');
		}		
		/**
		 * Запускаем проверку корректности ввода полей при добалении блога
		 */
		if (!$this->checkBlogFields()) {
			return false;	
		}
		$this->Security_ValidateSendForm();		
		/**
		 * Если всё ок то пытаемся создать блог
		 */
		$oBlog=Engine::GetEntity('Blog');
		$oBlog->setOwnerId($this->oUserCurrent->getId());
		$oBlog->setTitle(getRequest('blog_title'));
		/**
		 * Парсим текст на предмет разных ХТМЛ тегов
		 */
		$sText=$this->Text_Parser(getRequest('blog_description'));				
		$oBlog->setDescription($sText);
		$oBlog->setType(getRequest('blog_type'));
		$oBlog->setDateAdd(date("Y-m-d H:i:s"));
		$oBlog->setLimitRatingTopic(getRequest('blog_limit_rating_topic'));
		$oBlog->setUrl(getRequest('blog_url'));
		$oBlog->setAvatar(0);
		$oBlog->setAvatarType(null);
		/**
		* Загрузка аватара, делаем ресайзы
		*/			
		if (isset($_FILES['avatar']) and is_uploaded_file($_FILES['avatar']['tmp_name'])) {
			if ($sExtension=$this->Image_UploadBlogAvatar($_FILES['avatar'],$oBlog)) {
				$oBlog->setAvatar(1);
				$oBlog->setAvatarType($sExtension);
			} else {
				$this->Message_AddError($this->Lang_Get('blog_create_avatar_error'),$this->Lang_Get('error'));
				return false;
			}
		}
		/**
		 * Создаём блог
		 */
		if ($this->Blog_AddBlog($oBlog)) {
			/**
			 * Получаем блог, это для получение полного пути блога, если он в будущем будет зависит от других сущностей(компании, юзер и т.п.)
			 */
			$oBlog->Blog_GetBlogById($oBlog->getId());
			Router::Location($oBlog->getUrlFull());
		} else {
			$this->Message_AddError($this->Lang_Get('system_error'),$this->Lang_Get('error'));
		}
	}
	
	/**
	 * Редактирование блога
	 *
	 * @return unknown
	 */
	protected function EventEditBlog() {		
		/**
		 * Меню
		 */
		$this->sMenuSubItemSelect='';
		$this->sMenuItemSelect='profile';
		
		/**
		 * Проверяем передан ли в УРЛе номер блога
		 */
		$sBlogId=$this->GetParam(0);
		if (!$oBlog=$this->Blog_GetBlogById($sBlogId)) {
			return parent::EventNotFound();
		}
		/**
		 * Проверяем тип блога
		 */
		if ($oBlog->getType()=='personal') {
			return parent::EventNotFound();
		}
		/**
		 * Проверям авторизован ли пользователь
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle($this->Lang_Get('not_access'),$this->Lang_Get('error'));
			return Router::Action('error');
		}
		/**
		 * Явлется ли авторизованный пользователь хозяином блога, либо его администратором
		 */
		$oBlogUser=$this->Blog_GetBlogUserByBlogIdAndUserId($oBlog->getId(),$this->oUserCurrent->getId());		
		$bIsAdministratorBlog=$oBlogUser ? $oBlogUser->getIsAdministrator() : false;
		if ($oBlog->getOwnerId()!=$this->oUserCurrent->getId()  and !$this->oUserCurrent->isAdministrator() and !$bIsAdministratorBlog) {
			return parent::EventNotFound();
		}			
		
		$this->Viewer_AddHtmlTitle($oBlog->getTitle());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('blog_edit'));
		
		$this->Viewer_Assign('oBlogEdit',$oBlog);
		/**
		 * Устанавливаем шалон для вывода
		 */		
		$this->SetTemplateAction('add');
		/**
		 * Если нажали кнопку "Сохранить"
		 */
		if (isset($_REQUEST['submit_blog_add'])) {
			$this->Security_ValidateSendForm();
			/**
			 * Запускаем проверку корректности ввода полей при редактировании блога
			 */
			if (!$this->checkBlogFields($oBlog)) {
				return false;
			}			
			$oBlog->setTitle(getRequest('blog_title'));
			/**
			 * Парсим описание блога на предмет ХТМЛ тегов
			 */
			$sText=$this->Text_Parser(getRequest('blog_description'));			
			$oBlog->setDescription($sText);
			$oBlog->setType(getRequest('blog_type'));
			$oBlog->setLimitRatingTopic(getRequest('blog_limit_rating_topic'));
			//$oBlog->setUrl(getRequest('blog_url'));	// запрещаем смену URL блога	
			/**
			* Загрузка аватара, делаем ресайзы
			*/			
			if (isset($_FILES['avatar']) and is_uploaded_file($_FILES['avatar']['tmp_name'])) {
				if ($sExtension=$this->Image_UploadBlogAvatar($_FILES['avatar'],$oBlog)) {
					$oBlog->setAvatar(1);
					$oBlog->setAvatarType($sExtension);
				} else {
					$this->Message_AddError($this->Lang_Get('blog_create_avatar_error'),$this->Lang_Get('error'));
					return false;
				}
			}
			/**
			 * Удалить аватар
			 */
			if (isset($_REQUEST['avatar_delete'])) {
				$oBlog->setAvatar(0);				
				$oBlog->setAvatarType(null);
				
				$this->Image_DeleteBlogAvatar($oBlog);
			}
			/**
			 * Обновляем блог
			 */
			if ($this->Blog_UpdateBlog($oBlog)) {
				Router::Location($oBlog->getUrlFull());
			} else {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return Router::Action('error');
			}
		} else {
			/**
			 * Загружаем данные в форму редактирования блога
			 */
			$_REQUEST['blog_title']=$oBlog->getTitle();
			$_REQUEST['blog_url']=$oBlog->getUrl();
			$_REQUEST['blog_type']=$oBlog->getType();
			$_REQUEST['blog_description']=$oBlog->getDescription();
			$_REQUEST['blog_limit_rating_topic']=$oBlog->getLimitRatingTopic();
			$_REQUEST['blog_id']=$oBlog->getId();
		}
		
		
	}
	/**
	 * Управление пользователями блога
	 *
	 * @return unknown
	 */
	protected function EventAdminBlog() {		
		/**
		 * Меню
		 */		
		$this->sMenuItemSelect='admin';
		$this->sMenuSubItemSelect='';		
		/**
		 * Проверяем передан ли в УРЛе номер блога
		 */
		$sBlogId=$this->GetParam(0);
		if (!$oBlog=$this->Blog_GetBlogById($sBlogId)) {
			return parent::EventNotFound();
		}
		/**
		 * Проверям авторизован ли пользователь
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle($this->Lang_Get('not_access'),$this->Lang_Get('error'));
			return Router::Action('error');
		}
		/**
		 * Явлется ли авторизованный пользователь хозяином блога, либо его администратором
		 */
		$oBlogUser=$this->Blog_GetBlogUserByBlogIdAndUserId($oBlog->getId(),$this->oUserCurrent->getId());		
		$bIsAdministratorBlog=$oBlogUser ? $oBlogUser->getIsAdministrator() : false;
		if ($oBlog->getOwnerId()!=$this->oUserCurrent->getId()  and !$this->oUserCurrent->isAdministrator() and !$bIsAdministratorBlog) {
			return parent::EventNotFound();
		}					
		/**
		 * Обрабатываем сохранение формы
		 */
		if (isset($_REQUEST['submit_blog_admin'])) {
			$this->Security_ValidateSendForm();
			$aUserRank=getRequest('user_rank',array());
			if (!is_array($aUserRank)) {
				$aUserRank=array();
			}
			foreach ($aUserRank as $sUserId => $sRank) {
				if (!($oBlogUser=$this->Blog_GetBlogUserByBlogIdAndUserId($oBlog->getId(),$sUserId))) {
					$this->Message_AddError($this->Lang_Get('system_error'),$this->Lang_Get('error'));
					break;
				}
				
				switch ($sRank) {
					case 'administrator':
						$oBlogUser->setUserRole(LsBlog::BLOG_USER_ROLE_ADMINISTRATOR);
						break;
					case 'moderator':
						$oBlogUser->setUserRole(LsBlog::BLOG_USER_ROLE_MODERATOR);
						break;
					default:
						$oBlogUser->setUserRole(LsBlog::BLOG_USER_ROLE_USER);
						break;
				}
				$this->Blog_UpdateRelationBlogUser($oBlogUser);
				$this->Message_AddNoticeSingle($this->Lang_Get('blog_admin_users_submit_ok'));
			}
		}
		/**
		 * Получаем список подписчиков блога
		 */
		$aBlogUsers=$this->Blog_GetBlogUsersByBlogId($oBlog->getId());		

		$this->Viewer_AddHtmlTitle($oBlog->getTitle());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('blog_admin'));
		
		$this->Viewer_Assign('oBlogEdit',$oBlog);
		$this->Viewer_Assign('aBlogUsers',$aBlogUsers);
		/**
		 * Устанавливаем шалон для вывода
		 */		
		$this->SetTemplateAction('admin');
		
		/**
		 * Если блог закрытый, получаем приглашенных 
		 * и добавляем блок-форму для приглашения 
		 */
		if($oBlog->getType()=='close') {
			$aBlogUsersInvited=$this->Blog_GetBlogUsersByBlogId($oBlog->getId(),LsBlog::BLOG_USER_ROLE_INVITE);				
			$this->Viewer_Assign('aBlogUsersInvited',$aBlogUsersInvited);
			$this->Viewer_AddBlock('right','actions/ActionBlog/invited.tpl');
		}
	}
	
	/**
	 * Проверка полей блога
	 *
	 * @return unknown
	 */
	protected function checkBlogFields($oBlog=null) {
		/**
		 * Проверяем только если была отправлена форма с данными
		 */
		if (!isset($_REQUEST['submit_blog_add'])) {
			$_REQUEST['blog_limit_rating_topic']=0;
			return false;
		}
		
		$bOk=true;
		/**
		* Проверяем есть ли название блога
		*/
		if (!func_check(getRequest('blog_title'),'text',2,200)) {
			$this->Message_AddError($this->Lang_Get('blog_create_title_error'),$this->Lang_Get('error'));
			$bOk=false;
		}
		/**
		 * Проверяем есть ли уже блог с таким названием
		 */
		if ($oBlogExists=$this->Blog_GetBlogByTitle(getRequest('blog_title'))) {
			if (!$oBlog or $oBlog->getId()!=$oBlogExists->getId()) {
				$this->Message_AddError($this->Lang_Get('blog_create_title_error_unique'),$this->Lang_Get('error'));
				$bOk=false;
			}
		}
		
		if (!$oBlog) {
			/**
			* Проверяем есть ли URL блога, с заменой всех пробельных символов на "_"
			* Проверка только в том случаи если создаём новый блог, т.к при редактировании URL нельзя менять
			*/		
			$blogUrl=preg_replace("/\s+/",'_',getRequest('blog_url'));
			$_REQUEST['blog_url']=$blogUrl;
			if (!func_check(getRequest('blog_url'),'login',2,50)) {
				$this->Message_AddError($this->Lang_Get('blog_create_url_error'),$this->Lang_Get('error'));
				$bOk=false;
			}
		}
		/**
		 * Проверяем на счет плохих УРЛов
		 */
		if (in_array(getRequest('blog_url'),$this->aBadBlogUrl)) {
			$this->Message_AddError($this->Lang_Get('blog_create_url_error_badword').' '.join(',',$this->aBadBlogUrl),$this->Lang_Get('error'));
			$bOk=false;
		}
		/**
		 * Проверяем есть ли уже блог с таким URL
		 */
		if ($oBlogExists=$this->Blog_GetBlogByUrl(getRequest('blog_url'))) {
			if (!$oBlog or $oBlog->getId()!=$oBlogExists->getId()) {
				$this->Message_AddError($this->Lang_Get('blog_create_url_error_unique'),$this->Lang_Get('error'));
				$bOk=false;
			}
		}
		/**
		 * Проверяем есть ли описание блога
		 */
		if (!func_check(getRequest('blog_description'),'text',10,3000)) {
			$this->Message_AddError($this->Lang_Get('blog_create_description_error'),$this->Lang_Get('error'));
			$bOk=false;
		}
		/**
		 * Проверяем доступные типы блога для создания
		 */
		if (!in_array(getRequest('blog_type'),array('open','close'))) {
			$this->Message_AddError($this->Lang_Get('blog_create_type_error'),$this->Lang_Get('error'));
			$bOk=false;
		}
		/**
		 * Преобразуем ограничение по рейтингу в число 
		 */				
		if (!func_check(getRequest('blog_limit_rating_topic'),'float')) {
			$this->Message_AddError($this->Lang_Get('blog_create_rating_error'),$this->Lang_Get('error'));
			$bOk=false;
		}		
		return $bOk;
	}
	
	/**
	 * Показ всех топиков
	 *
	 */
	protected function EventTopics() {
		$sShowType=$this->sCurrentEvent;
		/**
		 * Меню
		 */
		$this->sMenuSubItemSelect=$sShowType;
		/**
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(0,2) ? $this->GetParamEventMatch(0,2) : 1;			
		/**
		 * Получаем список топиков
		 */					
		$aResult=$this->Topic_GetTopicsCollective($iPage,Config::Get('module.topic.per_page'),$sShowType);
		$aTopics=$aResult['collection'];	
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.topic.per_page'),4,Router::GetPath('blog').$sShowType);
		/**
		 * Вызов хуков
		 */
		$this->Hook_Run('blog_show',array('sShowType'=>$sShowType));
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aTopics',$aTopics);
		$this->Viewer_Assign('aPaging',$aPaging);		
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('index');
	}	
	/**
	 * Показ топика
	 *
	 * @param unknown_type $sBlogUrl
	 * @param unknown_type $iTopicId
	 * @return unknown
	 */
	protected function EventShowTopic() {
		$sBlogUrl='';	
		if ($this->GetParamEventMatch(0,1)) {
			// из коллективного блога
			$sBlogUrl=$this->sCurrentEvent;
			$iTopicId=$this->GetParamEventMatch(0,1);
			$this->sMenuItemSelect='blog';			
		} else {
			// из персонального блога
			$iTopicId=$this->GetEventMatch(1);
			$this->sMenuItemSelect='log';			
		}
		$this->sMenuSubItemSelect='';					
		/**
		 * Проверяем есть ли такой топик
		 */
		if (!($oTopic=$this->Topic_GetTopicById($iTopicId))) {
			return parent::EventNotFound();
		}
		/**
		 * Проверяем права на просмотр топика
		 */
		if (!$oTopic->getPublish() and (!$this->oUserCurrent or ($this->oUserCurrent->getId()!=$oTopic->getUserId() and !$this->oUserCurrent->isAdministrator()))) {
			return parent::EventNotFound();
		}

		/**
		 * Определяем права на отображение записи из закрытого блога
		 */
		if($oTopic->getBlog()->getType()=='close' 
			and (!$this->oUserCurrent 
				|| !in_array(
						$oTopic->getBlog()->getId(),
						array_keys($this->Blog_GetAccessibleBlogsByUser($this->oUserCurrent))
					)
				)
			) {
			return parent::EventNotFound();
		}
		
		/**
		 * Если запросили топик из персонального блога то перенаправляем на страницу вывода коллективного топика
		 */
		if ($sBlogUrl!='' and $oTopic->getBlog()->getType()=='personal') {
			Router::Location($oTopic->getUrl());
		}
		/**
		 * Если запросили не персональный топик то перенаправляем на страницу для вывода коллективного топика
		 */
		if ($sBlogUrl=='' and $oTopic->getBlog()->getType()!='personal') {
			Router::Location($oTopic->getUrl());
		}
		/**
		 * Если номер топика правильный но УРЛ блога косяный то корректируем его и перенаправляем на нужный адрес
		 */
		if ($sBlogUrl!='' and $oTopic->getBlog()->getUrl()!=$sBlogUrl) {
			Router::Location($oTopic->getUrl());
		}
		/**
		 * Обрабатываем добавление коммента
		 */
		if (isset($_REQUEST['submit_comment'])) {
			$this->SubmitComment();
		}
		/**
		 * Достаём комменты к топику
		 */		
		$aReturn=$this->Comment_GetCommentsByTargetId($oTopic->getId(),'topic');
		$iMaxIdComment=$aReturn['iMaxIdComment'];	
		$aComments=$aReturn['comments'];

		/**
		 * Отмечаем дату прочтения топика
		 */
		if ($this->oUserCurrent) {
			$oTopicRead=Engine::GetEntity('Topic_TopicRead');
			$oTopicRead->setTopicId($oTopic->getId());
			$oTopicRead->setUserId($this->oUserCurrent->getId());
			$oTopicRead->setCommentCountLast($oTopic->getCountComment());
			$oTopicRead->setCommentIdLast($iMaxIdComment);
			$oTopicRead->setDateRead(date("Y-m-d H:i:s"));
			$this->Topic_SetTopicRead($oTopicRead);
		}
		/**
		 * Вызов хуков
		 */
		$this->Hook_Run('topic_show',array("oTopic"=>$oTopic));		
		/**
		 * Выставляем SEO данные
		 */
		$sTextSeo=preg_replace("/<.*>/Ui",' ',$oTopic->getText());
		$this->Viewer_SetHtmlDescription(func_text_words($sTextSeo,20));
		$this->Viewer_SetHtmlKeywords($oTopic->getTags());
		/**
		 * Загружаем переменные в шаблон
		 */				
		$this->Viewer_Assign('oTopic',$oTopic);
		$this->Viewer_Assign('aComments',$aComments);		
		$this->Viewer_Assign('iMaxIdComment',$iMaxIdComment);
		$this->Viewer_AddHtmlTitle($oTopic->getBlog()->getTitle());
		$this->Viewer_AddHtmlTitle($oTopic->getTitle());
		$this->Viewer_SetHtmlRssAlternate(Router::GetPath('rss').'comments/'.$oTopic->getId().'/',$oTopic->getTitle());
		/**
		 * Устанавливаем шаблон вывода
		 */	
		$this->SetTemplateAction('topic');
	}
	
	protected function EventShowBlog() {
		$sBlogUrl=$this->sCurrentEvent;		
		$sShowType=in_array($this->GetParamEventMatch(0,0),array('bad','new')) ? $this->GetParamEventMatch(0,0) : 'good';			
		/**
		 * Проверяем есть ли блог с таким УРЛ
		 */		
		if (!($oBlog=$this->Blog_GetBlogByUrl($sBlogUrl))) {
			return parent::EventNotFound();
		}
		/**
		 * Определяем права на отображение закрытого блога
		 */
		if($oBlog->getType()=='close' 
			and (!$this->oUserCurrent 
				|| !in_array(
						$oBlog->getId(),
						array_keys($this->Blog_GetAccessibleBlogsByUser($this->oUserCurrent))
					)
				)
			) {
			$bCloseBlog=true;
		} else {
			$bCloseBlog=false;
		}
			
		/**
		 * Меню
		 */
		$this->sMenuSubItemSelect=$sShowType;
		$this->sMenuSubBlogUrl=$oBlog->getUrlFull();		
		/**
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(0,2) ? $this->GetParamEventMatch(0,2) : 1;		
		/**
		 * Получаем список топиков
		 */	
		$aResult=$this->Topic_GetTopicsByBlog($oBlog,$iPage,Config::Get('module.topic.per_page'),$sShowType);	
		$aTopics=$aResult['collection'];	
		/**
		 * Формируем постраничность
		 */
		$aPaging=($sShowType=='good') 
			? $this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.topic.per_page'),4,rtrim($oBlog->getUrlFull(),'/'))
			: $this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.topic.per_page'),4,$oBlog->getUrlFull().$sShowType);			
		/**
		 * Получаем число новых топиков в текущем блоге
		 */
		$this->iCountTopicsBlogNew=$this->Topic_GetCountTopicsByBlogNew($oBlog);	
		/**
		 * Выставляем SEO данные
		 */
		$sTextSeo=preg_replace("/<.*>/Ui",' ',$oBlog->getDescription());
		$this->Viewer_SetHtmlDescription(func_text_words($sTextSeo,20));	
		/**
		 * Получаем список юзеров блога
		 */
		$aBlogUsers=$this->Blog_GetBlogUsersByBlogId($oBlog->getId(),LsBlog::BLOG_USER_ROLE_USER);
		$aBlogModerators=$this->Blog_GetBlogUsersByBlogId($oBlog->getId(),LsBlog::BLOG_USER_ROLE_MODERATOR);
		$aBlogAdministrators=$this->Blog_GetBlogUsersByBlogId($oBlog->getId(),LsBlog::BLOG_USER_ROLE_ADMINISTRATOR);
		/**
		 * Вызов хуков
		 */
		$this->Hook_Run('blog_collective_show',array('oBlog'=>$oBlog,'sShowType'=>$sShowType));
		/**
		 * Загружаем переменные в шаблон
		 */				
		$this->Viewer_Assign('aBlogUsers',$aBlogUsers);		
		$this->Viewer_Assign('aBlogModerators',$aBlogModerators);
		$this->Viewer_Assign('aBlogAdministrators',$aBlogAdministrators);
		$this->Viewer_Assign('iCountBlogUsers',count($aBlogUsers));
		$this->Viewer_Assign('iCountBlogModerators',count($aBlogModerators));
		$this->Viewer_Assign('iCountBlogAdministrators',count($aBlogAdministrators)+1);		
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aTopics',$aTopics);
		$this->Viewer_Assign('oBlog',$oBlog);
		$this->Viewer_Assign('bCloseBlog',$bCloseBlog);		
		$this->Viewer_AddHtmlTitle($oBlog->getTitle());
		$this->Viewer_SetHtmlRssAlternate(Router::GetPath('rss').'blog/'.$oBlog->getUrl().'/',$oBlog->getTitle());
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('blog');
	}
	/**
	 * Обработка добавление комментария к топику через ajax
	 *
	 */
	protected function AjaxAddComment() {
		$this->Viewer_SetResponseAjax();
		$this->SubmitComment();
	}	
	/**
	 * Обработка добавление комментария к топику
	 *	 
	 * @return unknown
	 */
	protected function SubmitComment() {
		/**
		 * Проверям авторизован ли пользователь
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;			
		}
		/**
		 * Проверяем топик
		 */
		if (!($oTopic=$this->Topic_GetTopicById(getRequest('cmt_target_id')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Возможность постить коммент в топик в черновиках
		 */
		if (!$oTopic->getPublish() and $this->oUserCurrent!=$oTopic->getUserId() and !$this->oUserCurrent->isAdministrator()) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверяем разрешено ли постить комменты
		*/
		if (!$this->ACL_CanPostComment($this->oUserCurrent) and !$this->oUserCurrent->isAdministrator()) {			
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_acl'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверяем разрешено ли постить комменты по времени
		*/
		if (!$this->ACL_CanPostCommentTime($this->oUserCurrent) and !$this->oUserCurrent->isAdministrator()) {			
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_limit'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверяем запрет на добавления коммента автором топика
		*/
		if ($oTopic->getForbidComment()) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_notallow'),$this->Lang_Get('error'));
			return;
		}	
		/**
		* Проверяем текст комментария
		*/
		$sText=$this->Text_Parser(getRequest('comment_text'));
		if (!func_check($sText,'text',2,10000)) {			
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_add_text_error'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверям на какой коммент отвечаем
		*/
		$sParentId=(int)getRequest('reply');
		if (!func_check($sParentId,'id')) {			
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		$oCommentParent=null;
		if ($sParentId!=0) {
			/**
			* Проверяем существует ли комментарий на который отвечаем
			*/
			if (!($oCommentParent=$this->Comment_GetCommentById($sParentId))) {				
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
			/**
			* Проверяем из одного топика ли новый коммент и тот на который отвечаем
			*/
			if ($oCommentParent->getTargetId()!=$oTopic->getId()) {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		} else {
			/**
			* Корневой комментарий
			*/
			$sParentId=null;
		}
		/**
		* Проверка на дублирующий коммент
		*/
		if ($this->Comment_GetCommentUnique($oTopic->getId(),'topic',$this->oUserCurrent->getId(),$sParentId,md5($sText))) {			
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_spam'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Создаём коммент
		*/
		$oCommentNew=Engine::GetEntity('Comment');
		$oCommentNew->setTargetId($oTopic->getId());
		$oCommentNew->setTargetType('topic');
		$oCommentNew->setUserId($this->oUserCurrent->getId());		
		$oCommentNew->setText($sText);
		$oCommentNew->setDate(date("Y-m-d H:i:s"));
		$oCommentNew->setUserIp(func_getIp());
		$oCommentNew->setPid($sParentId);
		$oCommentNew->setTextHash(md5($sText));
			
		/**
		* Добавляем коммент
		*/
		if ($this->Comment_AddComment($oCommentNew)) {			
			$this->Viewer_AssignAjax('sCommentId',$oCommentNew->getId());
			if ($oTopic->getPublish()) {
				/**
			 	* Добавляем коммент в прямой эфир если топик не в черновиках
			 	*/					
				$oCommentOnline=Engine::GetEntity('Comment_CommentOnline');
				$oCommentOnline->setTargetId($oCommentNew->getTargetId());
				$oCommentOnline->setTargetType($oCommentNew->getTargetType());
				$oCommentOnline->setCommentId($oCommentNew->getId());
				$this->Comment_AddCommentOnline($oCommentOnline);
			}
			/**
			* Сохраняем дату последнего коммента для юзера
			*/
			$this->oUserCurrent->setDateCommentLast(date("Y-m-d H:i:s"));
			$this->User_Update($this->oUserCurrent);
			/**
			* Отправка уведомления автору топика
			*/
			$oUserTopic=$oTopic->getUser();
			if ($oCommentNew->getUserId()!=$oUserTopic->getId()) {
				$this->Notify_SendCommentNewToAuthorTopic($oUserTopic,$oTopic,$oCommentNew,$this->oUserCurrent);
			}
			/**
			* Отправляем уведомление тому на чей коммент ответили
			*/
			if ($oCommentParent and $oCommentParent->getUserId()!=$oTopic->getUserId() and $oCommentNew->getUserId()!=$oCommentParent->getUserId()) {
				$oUserAuthorComment=$oCommentParent->getUser();
				$this->Notify_SendCommentReplyToAuthorParentComment($oUserAuthorComment,$oTopic,$oCommentNew,$this->oUserCurrent);
			}
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
		}
	}	
	
	/**
	 * Обработка ajax запроса на отправку 
	 * пользователям приглашения вступить в закрытый блог
	 */
	protected function AjaxAddBlogInvite() {
		$this->Viewer_SetResponseAjax();
		$sUsers=getRequest('users');
		$sBlogId=getRequest('idBlog');
		
		/**
		 * Если пользователь не авторизирован, возвращаем ошибку
		 */
		if (!$this->User_IsAuthorization()) {	
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		$this->oUserCurrent=$this->User_GetUserCurrent();
		/**
		 * Проверяем существование блога
		 */
		if(!$oBlog=$this->Blog_GetBlogById($sBlogId)) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;			
		}
		/**
		 * Проверяем, имеет ли право текущий пользователь добавлять invite в blog
		 */
		$oBlogUser=$this->Blog_GetBlogUserByBlogIdAndUserId($oBlog->getId(),$this->oUserCurrent->getId());		
		$bIsAdministratorBlog=$oBlogUser ? $oBlogUser->getIsAdministrator() : false;
		if ($oBlog->getOwnerId()!=$this->oUserCurrent->getId()  and !$this->oUserCurrent->isAdministrator() and !$bIsAdministratorBlog) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;	
		}		
		
		/**
		 * Получаем список пользователей блога (любого статуса)		 
		 */
		$aBlogUsers = $this->Blog_GetBlogUsersByBlogId(
			$oBlog->getId(),
			array(
				LsBlog::BLOG_USER_ROLE_REJECT,
				LsBlog::BLOG_USER_ROLE_INVITE,
				LsBlog::BLOG_USER_ROLE_USER,
				LsBlog::BLOG_USER_ROLE_MODERATOR,
				LsBlog::BLOG_USER_ROLE_ADMINISTRATOR
			)
		);
		$aUsers=explode(',',$sUsers);

		$aResult=array();
		/**
		 * Обрабатываем добавление по каждому из переданных логинов
		 */
		foreach ($aUsers as $sUser) {
			$sUser=trim($sUser);
			if ($sUser=='') {
				continue;
			}
			/**
			 * Если пользователь пытается добавить инвайт 
			 * самому себе, возвращаем ошибку
			 */
			if(strtolower($sUser)==strtolower($this->oUserCurrent->getLogin())) {
				$aResult[]=array(
					'bStateError'=>true,
					'sMsgTitle'=>$this->Lang_Get('error'),
					'sMsg'=>$this->Lang_Get('blog_user_invite_add_self')
				);										
				continue;
			}
			
			/**
			 * Если пользователь не найден или неактивен,
			 * возвращаем ошибку
			 */
			if (!$oUser=$this->User_GetUserByLogin($sUser) or $oUser->getActivate()!=1) {
				$aResult[]=array(
					'bStateError'=>true,
					'sMsgTitle'=>$this->Lang_Get('error'),
					'sMsg'=>$this->Lang_Get('user_not_found',array('login'=>$sUser)),
					'sUserLogin'=>$sUser
				);
				continue;
			}

			if(!isset($aBlogUsers[$oUser->getId()])) {
				/**
				 * Создаем нового блог-пользователя со статусом INVITED
				 */
				$oBlogUserNew=Engine::GetEntity('Blog_BlogUser');
				$oBlogUserNew->setBlogId($oBlog->getId());
				$oBlogUserNew->setUserId($oUser->getId());
				$oBlogUserNew->setUserRole(LsBlog::BLOG_USER_ROLE_INVITE);
				
				if($this->Blog_AddRelationBlogUser($oBlogUserNew)) {
					$aResult[]=array(
						'bStateError'=>false,
						'sMsgTitle'=>$this->Lang_Get('attention'),
						'sMsg'=>$this->Lang_Get('blog_user_invite_add_ok',array('login'=>$sUser)),
						'sUserLogin'=>$sUser,
						'sUserWebPath'=>$oUser->getUserWebPath()
					);
					$this->SendBlogInvite($oBlog,$oUser);
				} else {
					$aResult[]=array(
						'bStateError'=>true,
						'sMsgTitle'=>$this->Lang_Get('error'),
						'sMsg'=>$this->Lang_Get('system_error'),
						'sUserLogin'=>$sUser
					);					
				}
			} else {
				/**
				 * Попытка добавить приглашение уже существующему пользователю,
				 * возвращаем ошибку (сначала определяя ее точный текст)
				 */
				switch (true) {
					case ($aBlogUsers[$oUser->getId()]->getUserRole()==LsBlog::BLOG_USER_ROLE_INVITE):
						$sErrorMessage=$this->Lang_Get('blog_user_already_invited',array('login'=>$sUser));
						break;
					case ($aBlogUsers[$oUser->getId()]->getUserRole()>LsBlog::BLOG_USER_ROLE_GUEST):
						$sErrorMessage=$this->Lang_Get('blog_user_already_exists',array('login'=>$sUser));						
						break;
					case ($aBlogUsers[$oUser->getId()]->getUserRole()==LsBlog::BLOG_USER_ROLE_REJECT):
						$sErrorMessage=$this->Lang_Get('blog_user_already_reject',array('login'=>$sUser));						
						break;
					default:
						$sErrorMessage=$this->Lang_Get('system_error');
				}
				$aResult[]=array(
					'bStateError'=>true,
					'sMsgTitle'=>$this->Lang_Get('error'),
					'sMsg'=>$sErrorMessage,
					'sUserLogin'=>$sUser
				);
				continue;
			}		
		}
		
		/**
		 * Передаем во вьевер массив с результатами обработки по каждому пользователю
		 */
		$this->Viewer_AssignAjax('aUsers',$aResult);
	}

	/**
	 * Выполняет отправку приглашения в блог 
	 * (по внутренней почте и на email)
	 *
	 * @param BlogEntity_Blog $oBlog
	 * @param UserEntity_User $oUser
	 */
	protected function SendBlogInvite($oBlog,$oUser) {
		$sTitle=$this->Lang_Get(
			'blog_user_invite_title',
			array(
				'blog_title'=>$oBlog->getTitle()
			)
		);

		require_once Config::Get('path.root.engine').'/lib/external/XXTEA/encrypt.php';
		$sCode=$oBlog->getId().'_'.$oUser->getId();
		$sCode=urlencode(base64_encode(xxtea_encrypt($sCode, Config::Get('module.blog.encrypt'))));

		$aPath=array(
			'accept'=>Router::GetPath('blog').'invite/accept/?code='.$sCode,
			'reject'=>Router::GetPath('blog').'invite/reject/?code='.$sCode
		);

		$sText=$this->Lang_Get(
			'blog_user_invite_text',
			array(
				'login'=>$this->oUserCurrent->getLogin(),
				'accept_path'=>$aPath['accept'],
				'reject_path'=>$aPath['reject'],
				'blog_title'=>$oBlog->getTitle()
			)
		);
		$oTalk=$this->Talk_SendTalk($sTitle,$sText,$this->oUserCurrent,array($oUser),false,false);
		/**
		 * Отправляем пользователю заявку
		 */
		$this->Notify_SendBlogUserInvite(
			$oUser,$this->oUserCurrent,$oBlog,
			Router::GetPath('talk').'read/'.$oTalk->getId().'/'
		);
		/**
		 * Удаляем отправляющего юзера из переписки
		 */	
		$this->Talk_DeleteTalkUserByArray($oTalk->getId(),$this->oUserCurrent->getId());
	}
	
	/**
	 * Обработка отправленого пользователю приглашения вступить в блог
	 */
	protected function EventInviteBlog() {	
		require_once Config::Get('path.root.engine').'/lib/external/XXTEA/encrypt.php';
		$sCode=xxtea_decrypt(base64_decode(urldecode(getRequest('code'))), Config::Get('module.blog.encrypt'));
		list($sBlogId,$sUserId)=explode('_',$sCode,2);
		
		$sAction=$this->GetParam(0);
		
		/**
		 * Получаем текущего пользователя
		 */
		if(!$this->User_IsAuthorization()) {
			return $this->EventNotFound();
		}
		$this->oUserCurrent = $this->User_GetUserCurrent();
		/**
		 * Если приглашенный пользователь не является авторизированным
		 */
		if($this->oUserCurrent->getId()!=$sUserId) {
			return $this->EventNotFound();
		}
		/**
		 * Получаем указанный блог
		 */
		if((!$oBlog=$this->Blog_GetBlogById($sBlogId)) || $oBlog->getType()!='close') {
			return $this->EventNotFound();
		}
				
		/**
		 * Получаем связь "блог-пользователь" и проверяем,
		 * чтобы ее тип был INVITE или REJECT
		 */
		if(!$oBlogUser=$this->Blog_GetBlogUserByBlogIdAndUserId($oBlog->getId(),$this->oUserCurrent->getId())) {
			return $this->EventNotFound();
		}
		if($oBlogUser->getUserRole()>LsBlog::BLOG_USER_ROLE_GUEST) {
			$sMessage=$this->Lang_Get('blog_user_invite_already_done');
			$this->Message_AddError($sMessage,$this->Lang_Get('error'),true);
			Router::Location(Router::GetPath('talk'));
			return ;						
		}
		if(!in_array($oBlogUser->getUserRole(),array(LsBlog::BLOG_USER_ROLE_INVITE,LsBlog::BLOG_USER_ROLE_REJECT))) {
			$this->Message_AddError($this->Lang_Get('system_error'),$this->Lang_Get('error'),true);
			Router::Location(Router::GetPath('talk'));
			return ;
		}
		
		/**
		 * Обновляем роль пользователя до читателя
		 */
		$oBlogUser->setUserRole(($sAction=='accept')?LsBlog::BLOG_USER_ROLE_USER:LsBlog::BLOG_USER_ROLE_REJECT);
		if(!$this->Blog_UpdateRelationBlogUser($oBlogUser)) {
			$this->Message_AddError($this->Lang_Get('system_error'),$this->Lang_Get('error'),true);
			Router::Location(Router::GetPath('talk'));
			return ;						
		} 
		$sMessage = ($sAction=='accept')
			? $this->Lang_Get('blog_user_invite_accept')
			: $this->Lang_Get('blog_user_invite_reject');
		$this->Message_AddNotice($sMessage,$this->Lang_Get('attention'),true);
		
		Router::Location(Router::GetPath('talk'));		
	}
	
	/**
	 * Выполняется при завершении работы экшена
	 *
	 */
	public function EventShutdown() {		
		/**
		 * Загружаем в шаблон необходимые переменные
		 */
		$this->Viewer_Assign('sMenuHeadItemSelect',$this->sMenuHeadItemSelect);
		$this->Viewer_Assign('sMenuItemSelect',$this->sMenuItemSelect);
		$this->Viewer_Assign('sMenuSubItemSelect',$this->sMenuSubItemSelect);
		$this->Viewer_Assign('sMenuSubBlogUrl',$this->sMenuSubBlogUrl);
		$this->Viewer_Assign('iCountTopicsCollectiveNew',$this->iCountTopicsCollectiveNew);
		$this->Viewer_Assign('iCountTopicsPersonalNew',$this->iCountTopicsPersonalNew);
		$this->Viewer_Assign('iCountTopicsBlogNew',$this->iCountTopicsBlogNew);
		$this->Viewer_Assign('iCountTopicsNew',$this->iCountTopicsNew);
	}
}
?>