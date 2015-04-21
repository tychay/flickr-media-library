/**
 * Old-style media upload handler for Flickr
 *
 * This is going to be janky!
 */
(function ($, window, constants) {
   var sprintf = window.sprintf;

  /**
   * + Convert HTTPS URL into HTTP.
   * @param string url a URL string
   * @return string
   */
  convert_https_to_http = function(url) {
    return url.replace('https:', 'http:');
  };

  function FMLSearchDisplay() {
    var self = this;

    // flickr query params
    this.perpage = 50;
    this.page = 1;
    this.num_pages = 1;
    this.user_id = null;
    this.query = null;
    this.sort_by = null;
    this.photoset_id = null;
    
    // flickr results
    this.photos = [];
    this.photo_data = {};
    this.photosets = null;
    
    // form objects (initialized in onready)
    this.$select_main   = null;
    this.$search_query  = null;
    this.$search_filter = null;
    this.$photo_list    = null;
    this.$media_sidebar = null;
    this.$add_button    = null;
    this.$error_box     = null;
    this.$spinner       = null;
    this.nonce          = null;

    //self.alignments = ['alignment_none', 'alignment_left', 'alignment_center', 'alignment_right'];
    //self.flickr_url = '';
    //self.title_text = '';    

    // UTILITY FUNCTIONS
    /**
     * Generates an image url for a given photo
     *
     * Does not support https currently
     * @param  {object} photoObj hash of flickr response
     * @param  {string} size     the size suffix: s,q,t,m,n,-,z,c,b,h,k,o
     * @return {string}          URL to image on flickr
     */
    this.imgUrl = function(photoObj,size,is_https) {
      if ( size == '-' ) { size = ''; }
      if ( size ) { size = '_'+size; }
      return 'http://farm'+photoObj.farm+'.staticflickr.com/'+photoObj.server+'/'+photoObj.id+'_'+photoObj.secret+size+'.jpg';
    };

    /**
     * Generates web page url to a given photo on Flickr
     *
     * Does not support photo sets, https, or short urls currently
     * @param  {object} photoObj hash of photo on flickr response
     * @return {string}          url to photo page on flickr
     */
    this.webUrl = function(photoObj) {
      //if (photoObj.urls && photoObj.urls.url && photoObj.urls.url.typ)
      var ownername = (typeof photoObj.owner === 'string') ? photoObj.owner : photoObj.owner.username;
      return 'http://www.flickr.com/photos'+ownername+'/'+photoObj.id;
    };

    // SEARCH QUERY API
    /**
     * Search even triggers search for photos using Flickr API.
     * @param  {int} 0 if starting a new search, 1 if display next page
     * @return {boolean} false
     */
    this.searchPhoto = function(paging) {
      if ( paging === 0 ) {
        self.page = 1;
        switch ( self.$select_main.val() ) {
          case 'self':
            self.user_id = constants.flickr_user_id;
            self.photoset_id = null;
            self.sort_by = self.getFilterValue('sort');
            self.query = self.$search_query.val();
            break;
          case 'sets':
            self.user_id = constants.flickr_user_id;
            self.photoset_id = self.getFilterValue('sets');
            self.sort_by = null;
            self.query = '';
            //self.$search_query.val(''); // clear search field
            break;
          case 'all':
            self.user_id = null;
            self.photoset_id = null;
            self.sort_by = self.getFilterValue('sort');
            self.query = self.$search_query.val();
            break;
        }
        self.clearItems(true);
      } else {
        self.page += paging;
      }

      var query = {
        page: self.page,
        per_page: self.perpage,
        //extras: 'url_s,url_q,url_n,url_z'
      };
      if ( self.query ) {
        query.text = self.query;
      }
      if ( self.sort_by ) {
        query.sort = self.sort_by;
      }
      if ( self.user_id ) {
        query.user_id     = self.user_id;
        if ( self.photoset_id ) {
          query.photoset_id = self.photoset_id;
        }
      }

      if ( !query.text && !query.user_id ) {
        query.method = 'flickr.photos.getRecent';
      } else if ( query.photoset_id ){
        query.method = 'flickr.photosets.getPhotos';
      } else {
        query.method = 'flickr.photos.search';
      }

      //console.log(query);
      self.callFlickrApi(query, self.callbackSearchPhotos);
      return false;
    };

    /**
     * Get the value of the select filter (if the type is right)
     * @param  {string} filterType either 'sort' or 'set'
     * @return {mixed}             either NULL or the value of the select filter
     */
    this.getFilterValue = function(filterType) {
      if ( !self.$select_filter ) { return null; }
      if ( self.$select_filter.attr('data-type') != filterType ) {
        return null;
      }
      return self.$select_filter.val();
    };

    /**
     * + parse and show any photodata callback
     * @param  {object} data the json data returned by flickr (probably "photos" or "photoset")
     */
    this.callbackSearchPhotos = function(data) {
      if( !data ) return self.error(data); 
      if( !data.photos && !data.photoset ) return self.error(data);
      var photos = data.photos;
      if ( !photos ) photos = data.photoset;
      if ( photos.pages ) { self.num_pages = Number(photos.pages); }
      var list = photos.photo;
      if ( !list ) return self.error(data);
      if ( !list.length ) return self.error(data);

      // save photos
      for (var i=0; i<list.length; ++i) {
        var photo = list[i];


        photo.owner = (photo.owner) ? photo.owner : self.user_id;

        // https://www.flickr.com/services/api/misc.urls.html
        /*
        var flickr_url = null;
        if(1 == setting_photo_link) {
          flickr_url = 'http://farm'+photo.farm+'.staticflickr.com/'+photo.server+'/'+photo.id+'_'+photo.secret+'.jpg';
        } else if (0 == setting_photo_link) {
          flickr_url = 'http://www.flickr.com/photos/'+owner+'/'+photo.id+'/';
        }
        photo.url = 'http://www.flickr.com/photos/'+photo.owner+'/'+photot.id+'/';
        */
        var idx = (self.page-1)*self.perpage + i;
        self.photos[idx] = photo.id;
        self.addPhotoDataFromFlickr(photo.id,photo);
      }

      return self.renderPhotoList();
    };

    /**
     * Inject data from Flickr API calls into phto_data array
     *
     * {@see wp_prepare_attachment_for_js()}.
     * 
     * This is modeled after data that goes into the media template:
     *
     * - id: Post ID
     * - title
     * - filename: name of file
     * - url
     * - link
     * - alt: alt link
     * - author
     * - description: description text
     * - caption: caption of image
     * - name
     * - status
     * - uploadedTo
     * - date
     * - modified
     * - menuOrder
     * - mime
     * - type: should be 'image' for now
     * - subtype
     * - icon
     * - dateFormatted: date file uploaded
     * - nonces
     * - editLink: link to the edit page
     * - sizes
     * - width: width of image
     * - height: height of image
     * - fileLength
     * - compat
     * 
     * - sizes: ??
     * - size.url: img src?
     * - filesizeHumanReadable: file size in human readable terms
     * - uploading. can.remove, status userSettings: not supported
     * 
     * @param {Number} id    The flickr_id of the photo
     * @param {Object} photo The data from the flickrApi
     */
    this.addPhotoDataFromFlickr = function(id,photo) {
      // check to make sure ID is right
      if ( photo.id && (id != photo.id) ) {
        return;
      }

      // first time added info
      if ( !self.photo_data[id] ) {
        //console.log(photo);
        self.photo_data[id] = {
          _flickrData: photo,
          flickrId: photo.id,
          title: photo.title,
          id: 0,
          alt: '', //flickr doesn't have this
          description: ''//not on first call
        };
        return;
      }

      // everything else overwrites existing set
      var photo_data = self.photo_data[id];
      for( var idx in photo ) {
        photo_data._flickrData[idx] = photo[idx];
      }

      // extract flickr parameters
      var flickr_data = photo_data._flickrData;
      if ( flickr_data.title._content ) {
          photo_data.title = flickr_data.title._content;
      }
      if ( flickr_data.description && flickr_data.description._content ) {
          photo_data.description = flickr_data.description._content;
      }
      // TODO allow configuration to choose which date shows
      if ( flickr_data.dates && flickr_data.dates.posted ) {
          photo_data.date = flickr_data.dates.posted * 1000;
      }
    };

    /**
     * handle "Load More" button press by downloading another page of Flickr data
     * @return {boolean} false to override default behaviuor
     */
    this.loadMore = function() {
      $('#pagination').prop('disabled',true).val(constants.msg_loading); //dont allow to keep clicking on it
      self.searchPhoto(1); // get next page
      return false;
    };

    /**
     * + Handle change in main select box filter
     *
     * this = $select_main
     */
    this.changeSearchType = function() {
      switch ( self.$select_main.val() ) {
        case 'self':
          self.$search_query.show();
          self.renderFilterMenu('self');
          self.searchPhoto(0);
          break;
        case 'sets':
          self.$search_query.hide();
          self.getPhotoSetsList(true);
          //self.renderFilterMenu('sets');
          self.clearItems(true);
          break;
        case 'all':
          self.$search_query.show();
          self.renderFilterMenu('all');
          self.searchPhoto(0);
          break;
      }
    };

    this.changeFilterType = function() {
        self.searchPhoto(0);
    };

    /**
     * + Handle defocus of search field (if edited)
     *
     * $this = $search_query
     */
    this.blurSearchField = function() {
      // dont' force a refresh if query is unchanged
      if ( self.query == self.$search_query.val() ) {
        return;
      }
      self.searchPhoto(0);
    };

    /**
     * API to call flickr and get user's photosets list
     * @param  {boolean} forceApi Whether to force an API call to refresh the photo list
     */
    self.getPhotoSetsList = function(forceApi) {
      var params = {
        user_id: constants.flickr_user_id,
        format: 'json',
        method: 'flickr.photosets.getList'
      };

      self.callFlickrApi(params, function(data) {
        if ( !data || !data.photosets || !data.photosets.photoset ) {
          self.photosets = null;
          self.error(data);
          return;
        }
        self.photosets = data.photosets;
        self.renderFilterMenu('sets');
        self.$spinner.hide();
        if ( forceApi ) {
          self.searchPhoto(0);
        }
      });
    };

    self.getPhotoInfo = function(photoId) {
      // no matter what, don't call it twice
      self.photo_data[photoId].loaded = true;

      var params = {
        photo_id: photoId,
        format: 'json',
        method: 'flickr.photos.getInfo'
      };

      self.callFlickrApi(params, function(data) {
        if ( !data || !data.photo || !data.photo.id ) {
          self.error(data);
          return;
        }
        self.addPhotoDataFromFlickr(data.photo.id, data.photo);
        // make sure it's the same before rendering
        if (photoId == $('.attachment-details').attr('data-id') ) {
          // render udpated data
          self.renderPhotoInfo(photoId);
        }
        self.$spinner.hide();
      });
    };

    /**
     * + click event on image
     *
     * $this = li that triggered event
     * 
     * @param  {event} event event object of tirgger
     */
    this.showPhotoInfo = function(event) {
      var flickr_id  = $(this).attr('data-id'),
          photo_data = self.photo_data[flickr_id];
      // First check to see if I already have gotten all the info
      if ( photo_data.id || photo_data.loaded ) {
        self.renderPhotoInfo(flickr_id);
        self.renderAddButton(constants.msgs_add_btn.insert, false, flickr_id);
        return true;
      }
      // First check to see if it's already in media library
      self.callApi(
        'get_media_by_flickr_id',
        { 'flickr_id': flickr_id },
        function( data ) {
          // fml api error
          if ( data.status != 'ok') {
            self.handle_fml_error(data);
            return true; //cancel spinner
          }
          if ( data.post_id === 0 ) {
            // It doesn't exist yet in ML
            // call flickr API to get more information
            self.getPhotoInfo(flickr_id);
            // meanwhile, render what we have.
            self.renderPhotoInfo(flickr_id);
            return false; //since we are calling API again, don't stop spinner
          } else {
            // It is already in media library, so add the data we have
            //console.log(data.post);
            self.photo_data[flickr_id] = data.post;
            self.renderPhotoInfo(flickr_id);
            return true; //cancel spinner
          }
        },
        false //default error behavior
      );
      // activate insert button
      self.renderAddButton(constants.msgs_add_btn.add_to, false, flickr_id);
    };


    /**
     * + click on add button
     *
     * $this = "add to media library" button
     */
    this.clickAddButton = function(event) {
      var $this = $(this),
          disabled = $this.prop('disabled'),
          id=$this.attr('data-id');

      // don't support click if disabled
      if ( $this.prop('disabled') ) {
          event.preventDefault();
          return;
      }

      // disable button and rename
      self.renderAddButton(constants.msgs_add_btn.adding, true, id);
      // make ajax call to see if it exists
      self.callApi(
        'get_media_by_flickr_id',
        { flickr_id: id },
        self.callbackRequestExists,
        function(XHR, status, errorThrown) {
          self.renderAddButton(constants.msgs_add_btn.add_to, true, 0);
          return true; //do default action
        }
      );
      // don't go to href
      event.preventDefault();
    };

    this.callbackRequestExists = function(data) {
      if ( data.status != 'ok' ) {
        // TODO: handle work on error
        self.handle_fml_error(data);
        return true; //cancel spinner
      }
      if ( data.post_id === 0 ) {
        // it doesn't exist already, add the file
        self.callApi(
          'new_media_from_flickr_id',
          { flickr_id: data.flickr_id },
          self.callbackRequestAdd,
          function(XHR, status, errorThrown) {
            // TODO:
            self.renderAddButton(constants.msgs_add_btn.add_to, true, 0);
          }
        );
        return false; //making another call, don't cancel the spinner
      } else {
        // It already exists!
        // TODO Add all the information needed to render
        self.renderAddButton(constants.msgs_add_btn.insert, false, data.flickr_id);
        return true; //cancel spinner
      }
    };

    this.callbackRequestAdd = function(data) {
      if ( data.status != 'ok' ) {
        // TODO: handle work on error
        self.handle_fml_error(data);
        return true; //cancel spinner
      }
      //TODO do work
      console.log(data);
      self.renderAddButton(constants.msgs_add_btn.insert, false, data.flickr_id);

    };

    // APIs
    /**
     * Make call to WordPress AJAX API for Flickr Media Library 
     * 
     * @param  {String}   method           The API method to call
     * @param  {Object}   params           Other params to pass API
     * @param  {function} responseCallback callback function, return true if it can continue to do stuff
     * @param  {function|boolean} errorCallback  if false, do default, else callback function if XHR error
     * @return {null}
     */
    this.callApi = function(method, params, responseCallback, errorCallback) {
      self.$error_box.hide();
      self.$spinner.show();

      // compose rest of query
      params._ajax_nonce = self.nonce;
      params.action = constants.ajax_action_call;
      params.method = method;
      $.ajax( constants.ajax_url, {
        timeout: 15000,
        type: 'POST', //using "type" instead of "method" in case our jQuery older than 1.9
        data: params,
        dataType: 'json',
        success: function(data) {
          //console.log(data); //debugging
          if ( responseCallback(data) ) {
            self.$spinner.hide();
          }
        },
        error: function(XHR, status, errorThrown) {
          //self.renderAddButton(constants.msgs_add_btn.add_to, true, flickrId);
          if ( !errorCallback ) {
            self.handle_ajax_error(XHR, status, errorThrown);
          } else if ( errorCallback(XHR, status, errorThrown) ) {
            // will also turn off spinner
            self.handle_ajax_error(XHR, status, errorThrown);
          }
        }
      });

    };
    /**
     * Form and make a Flickr api call
     * @param  {object} params          hash of API call params
     * @param  {function} successCallback function to call on success
     */
    this.callFlickrApi = function(params, successCallback) {
      self.$error_box.hide();
      self.$spinner.show();

      params.format = 'json';
      params.nojsoncallback = 1;  // don't want give us JSONP response


      // first let's sign the request
      self.callApi(
        'sign_flickr_request',
        { request_data: JSON.stringify(params) },
        function(data) {
          // FML API error
          if (data.status != 'ok') {
            return self.handle_fml_error( data );
          } 
          //console.log(data.signed.params);
          // …now we call flickr
          $.ajax( data.signed.url, {
            //async: true, //ajax is already async
            timeout: 10000,
            type: 'POST',
            data: data.signed.params,
            dataType: 'json',
            success: function(data) {
              if ('undefined' !== typeof data.stat && 'ok' !== data.stat) {
                return self.handle_flickr_error(data.code || '', data.message || 'Flickr API returned an unknown error');
              }
              successCallback.call(self, data);
            },
            error: self.handle_ajax_error
          });
        },
        false //error is default
      );
    };
    
    /**
     * jQuery ajax error
     * @param  {object} XHR         [description]
     * @param  {string} status      status code of error (null, timeout, error, abort, parseerror)
     * @param  {string} errorThrown string description of error
     */
    this.handle_ajax_error = function(XHR, status, errorThrown) {
      return self._show_error( sprintf(constants.msg_ajax_error, status, errorThrown ) );
    };

    /**
     * Flickr API error thrown
     * @param  {string} code flickr error code
     * @param  {string} msg  flickr error message
     */
    this.handle_flickr_error = function(code, msg) {
      if ( msg === '') {
        return self._show_error( constants.msg_flickr_error_unknown );
      }
      return self._show_error( sprintf(constants.msg_flickr_error, code, msg ) );
    };

    /**
     * WordPress FML API error thrown
     * @param {object} data the ajax return
     */
    this.handle_fml_error = function(data) {
      return self._show_error( sprintf(constants.msg_fml_error, data.code, data.reason) );
    };

    /**
     * + handle parsing errors in flickr dataset
     * @param  {object} data the data returned by flickr
     */
    this.error = function(data) {
      self.clearItems(true); // error means we have issues, wipe everything

      // if empty results (of photos or photoset)
      if( data && data.photos && data.photos.photo ) {
        return self._show_error(flickr_errors[0]);
      }
      if ( data && data.photoset && data.photoset.photo ) {
        return self._show_error(flickr_errors[0]);
      }
      // this code should have been trapped already, but whatever…
      var code = data.code;
      if (!flickr_errors[code] ) {
        code = 999;
      }
      return self.handle_flickr_error(code, flickr_errors[code]);
    };

    // RENDERING OUTPUT
    /**
     * clear results
     * @param {boolean} wipeArray should we wipe array also
     */
    self.clearItems = function(wipeArray) {
      self.$photo_list.empty();
      if ( wipeArray ) {
        self.photos = [];

      }
      //$('#next_page').hide();
      //$('#prev_page').hide();
    };

    /**
     * output an error to the user.
     * @param  {[type]} msg [description]
     * @return {[type]}     [description]
     */
    this._show_error = function(msg) {
      self.$error_box.text(msg).show();
      self.$spinner.hide(); //hide any spinner just in case
    };

    /**
     * Display self.photos as a list in main box
     */
    this.renderPhotoList = function () {
      this.clearItems(false);

      for (var i=0; i<self.photos.length; ++i) {
        photo = self.photo_data[self.photos[i]];
        if ( typeof(photo) != 'object' ) { continue; } // should never happen, but let's handle sparse arrays just in case
        //var li = document.createElement('li');
        var $li = $('<li>').attr({
          tabindex: 0,
          role: 'checkbox',
          'aria-label': photo.title,
          'aria-checked': 'false',
          'data-id': photo.flickrId,
          class: 'attachment save-ready'
        }).click(self.showPhotoInfo);

        var $img = $('<img>').attr({
          src: self.imgUrl(photo._flickrData,'s'),
          srcset: self.imgUrl(photo._flickrData,'s')+' 1x, '+self.imgUrl(photo._flickrData,'q')+' 2x',
          draggable: 'false',
          alt: photo.alt,
          title: photo.title
        });

        $li.append($img);
        self.$photo_list.append($li);
      }

      if (self.page < self.num_pages) {
          self.renderPagination();
      }

      // check to see if picturefill exists and if so, call it
      self.$spinner.hide();
    };

    /**
     * Render pagination 
     */
    this.renderPagination = function() {
      //<li id="pagin"><input id="pagination" type="button" class="button" value="Load More" style="display: inline-block;"></li>
      var $li = $('<li>').attr({
        id:    'pagin'
        //style: 'display:block;'
      });
      var $input = $('<input>').attr({
        id:      'pagination',
        type:    'button',
        'class': 'button',
        value:    constants.msg_pagination
        //style:   'display:inline-block;'
      }).click(self.loadMore);
      self.$photo_list.append($li.append($input));
    };

    /**
     * Create secondary filter select box
     */
    this.renderFilterMenu = function(searchType) {
      switch ( searchType) {
        case 'self':
        case 'all':
          if ( self.$select_filter.attr('data-type') != 'sort' ) {
            self.renderSortMenu();
          }
          break;
        case 'sets':
          if ( self.$select_filter.attr('data-type') != 'sets' ) {
            self.renderSetsMenu();
          }
      }
    };

    /**
     * Render the sort_by select menu in the filter section
     */
    this.renderSortMenu = function() {
      var $select_filter = self.$select_filter;

      $select_filter.hide();
      $select_filter.empty();
      for (var val in constants.msgs_sort) {
        //var option = $('<option>').attr('value', val).text(constants.msgs_sort[val]);
        //$select_filter.append(option);
        $select_filter.append(
          new Option(constants.msgs_sort[val], val)
        );
      }
      $select_filter.attr('data-type','sort');
      $select_filter.show();
    };

    /**
     * Render the photo sets select menu in the filters section
     */
    this.renderSetsMenu = function() {
      var $select_filter = self.$select_filter;

      $select_filter.hide();
      $select_filter.empty();
      if ( !self.photosets) { return; }
      for( var i=0; i<self.photosets.photoset.length; ++i) {
        var short_name = (self.photosets.photoset[i].title._content.replace(/^(.{17}).*$/, '$1…'));
        $select_filter.append(
          new Option(short_name, self.photosets.photoset[i].id)
        );
      }
      $select_filter.attr('data-type','sets');
      $select_filter.show();
    };

    /**
     * Render a photo onto the sidebar
     * @param  {string} id flickr id of photo (data-id of li element clicked on)
     * @return null     
     */
    this.renderPhotoInfo = function(id) {
      this.$media_sidebar.empty();

      var photo_data = self.photo_data[id];
      // if ( !photo_data ) { ???; } TODO
      //console.log(photo_data);

      var info_box = $('<div>').attr({
        tabindex: 0,
        'data-id': id,
        'class': 'attachment-details save-ready'
      }).append(
        $('<h3>').text(constants.msg_attachment_details)
      );

      // TODO: remove dependency on core _flickrData
      var attachment_info = $('<div>').attr('class', 'attachment-info').append(
        $('<div>').attr('class','thumbnail thumbnail-image')
        .append(
          $('<img>').attr({
            src: self.imgUrl(photo_data._flickrData,'m'),
            srcset: self.imgUrl(photo_data._flickrData,'m')+' 1x, '+self.imgUrl(photo_data._flickrData,'z')+' 2x, '+self.imgUrl(photo_data._flickrData,'c')+' 3x',
            draggable: 'false',
            title: photo_data.title,
            alt: photo_data.alt
          })
        )
      );

      var details = $('<div>').attr('class', 'details');
      details.append($('<div>').attr('class', 'filename').text(photo_data.title));
      // TODO: fix dates
      if ( photo_data.date ) {
        var date = new Date(parseInt(photo_data.date));
        details.append($('<div>').attr('class', 'uploaded').text(date.toLocaleString()));
      }
      // file-size
      // dimensions
      // X edit attachment link
      // X refresh attachment link
      // X delete attachment link
      // <div class="compat-meta"></div>
      attachment_info.append(details);
      info_box.append(attachment_info);
      
      info_box.append(self._makeLabelTag('url', constants.msg_url, self.webUrl(photo_data._flickrData), false ));
      info_box.append(self._makeLabelTag('title', constants.msg_title, photo_data.title, false ));
      // label title
      // label caption
      // label alt text
      if ( photo_data.description ) {
        info_box.append(self._makeLabelTag('description', constants.msg_description, photo_data.description, true ));
      }

      this.$media_sidebar.append(info_box);
    };

    this._makeLabelTag = function(dataSetting, name, value, textArea)  {
      var label = $('<label>').attr({
          'class': 'setting',
          'data-setting': dataSetting
        }).append(
          $('<span>').attr('class','name').text(name)
        );
      if ( textArea ) {
        label.append(
          $('<textarea>').attr('readonly', 'readonly').text(value)
        );
      } else {
        label.append(
          $('<input>').attr({
            'type': 'text',
            'value': value,
            'readonly': 'readonly'
          })
        );
      }
      return label;
    };

    /**
     * Control the display of the add button on the form.
     * 
     * @param  {String} msg      text of add button
     * @param  {bool}   disabled set disable state
     * @param  {Number} id       the flickrId to set it to, or 0 to remvoe
     * @return {null} 
     */
    this.renderAddButton = function( msg, disabled, id ) {
      // prop() doesn't seem to work :-(
      if (id) {
        self.$add_button.attr({
          'disabled': disabled,
          'data-id' : id
        }).text(msg);
      } else {
        self.$add_button.attr('disabled', disabled).removeAttr('data-id').text(msg);
      }
    };

    // ONREADY
    $( function() {
      // initialize document element properties
      self.$select_main   = $('#'+constants.slug+'-select-main');
      self.$select_filter = $('#'+constants.slug+'-select-filtersort');
      self.$search_query  = $('#'+constants.slug+'-search-input');
      self.$photo_list    = $('#'+constants.slug+'-photo-list');
      self.$media_sidebar = $('#'+constants.slug+'-media-sidebar');
      self.$add_button    = $('#'+constants.slug+'-media-add-button');
      self.$error_box     = $('#'+constants.slug+'-ajax-error');
      self.$spinner       = $('.spinner');
      self.nonce          = $('#'+constants.slug+'-search-nonce').val();

      // render
      self.renderFilterMenu('self');

      // bind behaviors
      self.$select_main.on(  'change',self.changeSearchType);
      self.$select_filter.on('change',self.changeFilterType);
      self.$search_query.on( 'blur',  self.blurSearchField);
      self.$add_button.on(   'click', self.clickAddButton);
    });
  } // of FMLSearchDisplay class
  //var wpFlickrEmbed = new WpFlickrEmbed();
  fmlSearchDisplay = new FMLSearchDisplay();
	// on ready function
	$( function() {
    fmlSearchDisplay.searchPhoto(0);
	});

})(jQuery, window, FMLConst);
/*
        self.photos[photo.id] = new Object();
        self.photos[photo.id].title = photo.title;
        self.photos[photo.id].flickr_url = flickr_url;        

        var div = document.createElement('div');
        $(div).addClass('flickr_photo');

        var img = document.createElement('img');
        img.setAttribute('src', image_s_url);
        img.setAttribute('alt', photo.title);
        img.setAttribute('title', photo.title);
        img.setAttribute('rel', photo.id);
        $(img).addClass('flickr_image');
        $(img).click(function() {
          window['wpFlickrEmbed'].showInsertImageDialog($(this).attr('rel'));
        });

        var atag = document.createElement('a');
        atag.href = flickr_url;
        atag.title = atag.tip = "show on Flickr";
        atag.target = '_blank';
        atag.innerHTML = '<img src="'+plugin_img_uri+'/show-flickr.gif" alt="show on Flickr"/>';

        var title = document.createElement('div');
        $(title).addClass('flickr_title');

        var span = document.createElement('span');
        span.innerHTML = photo.short_title.replace(/(.{3})/g, '$1&wbr;').htmlspecialchars().replace(/&amp;wbr;/g, '<wbr/>');
        span.setAttribute('title', photo.title);
        span.setAttribute('rel', photo.id);
        $(span).click(function() {
          window['wpFlickrEmbed'].showInsertImageDialog($(this).attr('rel'));
        });

        title.appendChild(atag);
        title.innerHTML += '&nbsp;';
        title.appendChild(span);

        div.appendChild(img);
        div.appendChild(title);

        $('#items').append(div);
        $('#loader').hide();
*/
/**
 * Taken from WP Flickr Embed.
 */
/*
var $ = jQuery;

String.prototype.htmlspecialchars = function() {
  var str = this;
  str = str.replace(/&/g,"&amp;");
  str = str.replace(/"/g,"&quot;");
  str = str.replace(/'/g,"&#039;");
  str = str.replace(/</g,"&lt;");
  str = str.replace(/>/g,"&gt;");
  return str;
};

function WpFlickrEmbed() {

  self.slugifySizeLabel = function(sizeLabel) {
    return sizeLabel.toLowerCase().replace(' ', '_');
  };

  self.flickrGetPhotoSizes = function(photo_id) {
    var params = {};
    params.photo_id = photo_id;
    params.method = 'flickr.photos.getSizes';
    params.time = (new Date()).getTime();

    self.getFlickrData(params, self.callbackPhotoSizes);
  };

  /**
   * Build DIV containing Radio button for selecting a size.
   * @return {*}
   *
  self.buildSizeSelectorRadioButtonDiv = function(sizeObj) {
    var size = sizeObj.slug;

    // e.g. 'medium_600' -> 'medium'
    var sizeCategory = (size.split('_') || [size])[0];
    if ('large_square' == sizeObj.slug) sizeCategory = 'square';

    var newSizeRadioInputId = sizeObj.idPrefix + '_' + size;

    var newSizeRadio = $('<input type="radio" />');
    newSizeRadio.attr({
      id: newSizeRadioInputId,
      name: sizeObj.idPrefix + '_size',
      value: size
    });
    newSizeRadio.attr('data-sizeCategory', sizeCategory);
    newSizeRadio.attr('data-width', sizeObj.width || '');
    newSizeRadio.attr('data-height', sizeObj.height || '');

    if (sizeObj.imgSrc && '' != sizeObj.imgSrc) {
      newSizeRadio.attr('rel', sizeObj.imgSrc);
    } else {
      newSizeRadio.attr('disabled', 'disabled');
    }

    var newSizeLabel = $('<label />');
    newSizeLabel.attr('for', newSizeRadioInputId);
    newSizeLabel.text(sizeObj.label);

    var newSizeDiv = $('<div />');
    newSizeDiv.addClass(size);
    newSizeDiv.append(newSizeRadio).append('<span>&nbsp;</span>').append(newSizeLabel);

    return newSizeDiv;
  };

  self.callbackPhotoSizes = function(data) {
    if (! data) return self.error(data);
    if (! data.sizes) return self.error(data);
    var list = data.sizes.size;
    if (! list) return self.error(data);
    if (! list.length) return self.error(data);

    var jqDisplaySizeDiv = $('#select_size div.sizes').empty();
    var jqLightboxSizeDiv = $('#select_lightbox_size div.sizes').empty();

    var originalSizeIncluded = false;

    for (i=0; i<list.length; ++i) {
      originalSizeIncluded = ('Original' == list[i].label);

      jqDisplaySizeDiv.append(self.buildSizeSelectorRadioButtonDiv({
        idPrefix: 'display',
        slug: self.slugifySizeLabel(list[i].label),
        imgSrc: list[i].source,
        width: list[i].width,
        height: list[i].height,
        label: list[i].label + ' (' + list[i].width + ' x ' + list[i].height + ')'
      }));

      jqLightboxSizeDiv.append(self.buildSizeSelectorRadioButtonDiv({
        idPrefix: 'lightbox',
        slug: self.slugifySizeLabel(list[i].label),
        imgSrc: list[i].source,
        width: list[i].width,
        height: list[i].height,
        label: list[i].label + ' (' + list[i].width + ' x ' + list[i].height + ')'
      }));
    }

    // original size disabled?
    if (!originalSizeIncluded) {
      jqDisplaySizeDiv.append(self.buildSizeSelectorRadioButtonDiv({
        idPrefix: 'display',
        slug: 'original',
        label: 'Original (not permitted)'
      }));

      jqLightboxSizeDiv.append(self.buildSizeSelectorRadioButtonDiv({
        idPrefix: 'lightbox',
        slug: 'original',
        label: 'Original (not permitted)'
      }));
    }

    jqDisplaySizeDiv.find(':radio').first().click();
    jqLightboxSizeDiv.find(':radio').first().click();

    $('#loader').hide();
    $('#put_dialog').show();
    $('#put_background').show();
  };


  self.showInsertImageDialog = function(photo_id) {
    self.flickr_url = self.photos[photo_id].flickr_url;
    self.title_text = self.photos[photo_id].title;

    self.flickrGetPhotoSizes(photo_id);

    if(!$('#select_alignment :radio:checked').size()) {
      $('#alignment_none').attr('checked', 'checked');
    }

    $('#photo_title').val(self.title_text);
  };

  self.insertImage = function() {
    var original_flickr_url = self.flickr_url,
      flickr_url = original_flickr_url;

    var title_text = $.trim($('#photo_title').val());
    if ('' == title_text) {
      alert('Please enter a title for the photo');
      return;
    }

    var img_url, img_width, img_height = null;
    if(0 < $('#select_size :radio:checked').size()) {
      var selectedSize = $('#select_size :radio:checked');
      img_url = self.convertHTTPStoHTTP(selectedSize.attr('rel'));
      img_width = selectedSize.attr('data-width');
      img_height = selectedSize.attr('data-height');
    }

    if(0 < $('#select_lightbox_size :radio:checked').size()) {
      flickr_url = self.convertHTTPStoHTTP($('#select_lightbox_size :radio:checked').attr('rel'));
    }

    var img = $('<img />');
    img.attr('src', img_url)
        .attr('width', img_width)
        .attr('height', img_height)
        .attr('alt', title_text)
        .attr('title', title_text);

    var a = $('<a />');
    a.attr('href', (flickr_url ? flickr_url : '#')).attr('title', title_text).attr('rel', setting_link_rel);
    a.addClass(setting_link_class);

    var p = $('<p />');

    var alignment = null;
    if($('#alignments :radio:checked').size()) {
      alignment = $('#alignments :radio:checked').val();
    }

    if(alignment != 'none') {
      if(alignment != 'center') {
        img.css('float', alignment).addClass('align'+alignment);
      }else{
        img.addClass('aligncenter');
        p.css('text-align', 'center');
      }
    }

    a.append(img);
    p.append(a);

    $('#put_dialog').hide();
    $('#put_background').hide();

    self.send_to_editor(p.html(), $('#continue_insert:checked').size() == 0);
  };

  self.cancelInsertImage = function() {
    $('#put_dialog').hide();
    $('#put_background').hide();
  };

  self.changeSize = function(e) {
    var elem = $(e.target);

    var sizeCategory = elem.attr('data-sizeCategory');
    if (!sizeCategory) return;

    var preview_img = elem.closest('.selector').find('.size_preview img');
    if (0 >= preview_img.size()) return;

    if(preview_img.attr('rel') != sizeCategory) {
      preview_img.attr('rel', sizeCategory);
      preview_img.attr('src', plugin_img_uri+'/size_'+sizeCategory+'.png');
    }
  };

  self.changeAlignment = function() {
    var alignment = null;
    if($('#alignments :radio:checked').size()) {
      alignment = $('#alignments :radio:checked').val();
    }
    if(alignment && $('#alignment_image').attr('rel') != alignment) {
      $('#alignment_preview').html('<img id="alignment_image" rel="'+alignment+'" src="'+plugin_img_uri+'/alignment_'+alignment+'.png" alt=""/>');
    }
  };

  self.send_to_editor = function(h, close) {
    var ed;

    if ( typeof top.tinyMCE != 'undefined' && ( ed = top.tinyMCE.activeEditor ) && !ed.isHidden() ) {
      // restore caret position on IE
      if ( top.tinymce.isIE && ed.windowManager.insertimagebookmark )
        ed.selection.moveToBookmark(ed.windowManager.insertimagebookmark);

      if ( h.indexOf('[caption') === 0 ) {
        if ( ed.plugins.wpeditimage )
          h = ed.plugins.wpeditimage._do_shcode(h);
      } else if ( h.indexOf('[gallery') === 0 ) {
        if ( ed.plugins.wpgallery )
          h = ed.plugins.wpgallery._do_gallery(h);
      } else if ( h.indexOf('[embed') === 0 ) {
        if ( ed.plugins.wordpress )
          h = ed.plugins.wordpress._setEmbed(h);
      }

      ed.execCommand('mceInsertContent', false, h);
      $('iframe#tinymce:first').contents().find('img').each(function() { self.src = self.src });

    } else if ( typeof top.edInsertContent == 'function' ) {
      top.edInsertContent(top.edCanvas, h);
    } else {
      top.jQuery( top.edCanvas ).val( top.jQuery( top.edCanvas ).val() + h );
    }

    if(close) {
      top.tb_remove();
    }
  };
}



  insertShortcode = function(name) {
      var win = window.dialogArguments || opener || parent || top;
      var shortcode='[testcode name='+name+']';
      win.send_to_editor(shortcode);
    }
 
  $(function () {
    $('#insert_shortcode').bind('click',function() {
        var name = $('#name').val();
        insertShortcode(name);
    });
  });
*/
