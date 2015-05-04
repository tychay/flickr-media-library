/**
 * Old-style media upload handler for Flickr
 *
 * This is going to be janky!
 */
(function ($, window, constants) {
  var sprintf = window.sprintf;
  //console.log(constants);

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
      return 'http://www.flickr.com/photos/'+ownername+'/'+photoObj.id;
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
     * Inject data from Flickr API calls into photo_data array
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
      $('#pagination').prop('disabled',true).val(constants.msgs_pagination.loading); //dont allow to keep clicking on it
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
          // render updated data
          self.renderPhotoInfo(photoId);
          self.guessRenderAddButton(photoId);
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
      // Disable from future clicks
      self.renderAddButton(constants.msgs_add_btn.query, true, flickr_id);
      // First check to see if I already have gotten all the info
      if ( photo_data.id || photo_data.loaded ) {
        self.renderPhotoInfo(flickr_id);
        self.guessRenderAddButton(flickr_id);
        return true;
      }
      // First check to see if it's already in media library
      self.callApi(
        'get_media_by_flickr_id',
        { 'flickr_id': flickr_id },
        self.callbackFMLPostSuccess,
        function(XHR,status,errorThrown) {
          self.guessRenderAddButton(flickr_id);
        }
      );
    };

    /**
     * Handle return on a query of if FML post exists or if FML post created
     * 
     * @param  {Object} data AJAX response. Has status, flickr_id, post_id, post
     * @return {bool}        whether to stop the spinner
     */
    this.callbackFMLPostSuccess = function(data) {
      // fml api error
      if ( data.status != 'ok') {
        self.handle_fml_error(data);
        self.guessRenderAddButton(0);
        return true; //cancel spinner
      }
      // this should never happen after an ADD, just when CLICKING on something
      // NEW
      if ( data.post_id === 0 ) {
        // It doesn't exist yet in ML
        // call flickr API to get more information
        self.getPhotoInfo(data.flickr_id);
        // meanwhile, render what we have.
        self.renderPhotoInfo(data.flickr_id);
        // keep the button disabled
        return false; //since we are calling API again, don't stop spinner
      } else {
        // It is already in media library, so add the data we have
        //console.log(data.post);
        self.photo_data[data.flickr_id] = data.post;
        self.renderPhotoInfo(data.flickr_id);
        self.guessRenderAddButton(data.flickr_id);
        return true; //cancel spinner
      }
    };

    /**
     * + click on add button (for insert media iframe)
     *
     * $this = "add to" button
     *
     * There are two behaviors:
     * - if it is in media library already: inject HTML shortcode into page
     * - if it is not in library: add to media library
     */
    this.clickAddButtonInsertPost = function(event) {
      var $this    = $(this),
          disabled = $this.prop('disabled');

      // don't support click if disabled
      if ( $this.prop('disabled') ) {
          event.preventDefault();
          return;
      }

      var id         = $this.attr('data-id'),
          photo_data = self.photo_data[id];

      if ( photo_data.id ) {
        //It's already in library: API get the HTML to inject
        var params = {
          'attachment[id]'          : photo_data.id,
          'attachment[image_alt]'   : $('label[data-setting=alt] input').val(),
          'attachment[post_excerpt]': $('label[data-setting=caption] textarea').val(),
          'attachment[align]'       : $('select[data-setting=align]').val(),
          'attachment[image-size]'  : $('select[data-setting=size]').val(),
          'attachment[link]'        : $('select[data-setting=link]').val()
        };
        if ( constants.post_id ) { params.post_id = post_id; }
        //console.log(params);
        self.callApi(
          'send_attachment_to_editor',
          params,
          function(data) {
            if ( data.status != 'ok') {
              self.handle_fml_error(data);
              self.guessRenderAddButton(0);
              return true; //cancel spinner
            }
            self.guessRenderAddButton(0);
            //console.log(data);
            // Inject code into parent frame
            var win = window.dialogArguments || opener || parent || top;
            win.send_to_editor(data.html);
          },
          function(XHR, status, errorThrown) {
            self.guessRenderAddButton(0);
            return true; //do default action
          }
        );
        // disable button and rename
        self.renderAddButton(constants.msgs_add_btn.adding, true, id);
      } else {
        // Not in library yet
        // disable button and rename
        self.renderAddButton(constants.msgs_add_btn.adding, true, id);
        // call api to add it to media library
        self.callApi(
          'new_media_from_flickr_id',
          {
            flickr_id: id,
            alt: $('label[data-setting=alt] input').val(),
            caption: $('label[data-setting=caption] textarea').val()
          },
          self.callbackFMLPostSuccess, //Return looks exactly like a if exists query on a match
          function(XHR, status, errorThrown) {
            self.guessRenderAddButton(0);
            return true; //do default action
          }
        );
      }
      // don't go to href
      event.preventDefault();
    };
    /**
     * + click on add button (for setting featured image)
     *
     * $this = "add to" button
     *
     * There are two behaviors:
     * - if it is in media library already: ajax set post thumbnail and clsoe
     * - if it is not in library: add to media library
     */
    this.clickAddButtonSetFeaturedImage = function(event) {
      var $this    = $(this),
          disabled = $this.prop('disabled');

      // don't support click if disabled
      if ( $this.prop('disabled') ) {
          event.preventDefault();
          return;
      }

      var id         = $this.attr('data-id'),
          photo_data = self.photo_data[id];

      if ( photo_data.id ) {
        //It's already in library: Call function to set as thumbnail (and get HTML)
        WPSetAsThumbnail( photo_data.id, constants.nonce_set_thumbnail );
        tb_close();
        return true;
      } else {
        // Not in library yet
        // disable button and rename
        self.renderAddButton(constants.msgs_add_btn.adding, true, id);
        // call api to add it to media library
        self.callApi(
          'new_media_from_flickr_id',
          {
            flickr_id: id,
            alt: $('label[data-setting=alt] input').val(),
            caption: $('label[data-setting=caption] textarea').val()
          },
          self.callbackFMLPostSuccess, //Return looks exactly like a if exists query on a match
          function(XHR, status, errorThrown) {
            self.guessRenderAddButton(0);
            return true; //do default action
          }
        );
      }
      // don't go to href
      event.preventDefault();
    };
    /**
     * + click on add button (for add flickr "overlay" admin menu)
     *
     * $this = "add to" button
     *
     * There are two behaviors
     * - if it is media library already: jump to edit page on item
     * - if it is not in library: add to media library
     * @param  {[type]} event [description]
     * @return {[type]}       [description]
     */
    this.clickAddButtonMediaLibrary = function(event) {
      var $this    = $(this),
          disabled = $this.prop('disabled');

      // don't support click if disabled
      if ( $this.prop('disabled') ) {
          event.preventDefault();
          return;
      }

      var id         = $this.attr('data-id'),
          photo_data = self.photo_data[id];

      if ( photo_data.id ) {
        window.location = sprintf(constants.edit_url_format, photo_data.id);
      } else {
        // Not in library yet
        // disable button and rename
        self.renderAddButton(constants.msgs_add_btn.adding, true, id);
        // call api to add it to media library
        self.callApi(
          'new_media_from_flickr_id',
          {
            flickr_id: id,
            alt: $('label[data-setting=alt] input').val(),
            caption: $('label[data-setting=caption] textarea').val()
          },
          function(data) {
            // fml api error
            if ( data.status != 'ok') {
              self.handle_fml_error(data);
              self.guessRenderAddButton(0);
              return true; //cancel spinner
            }
            // jump to edit
            window.location = sprintf(constants.edit_url_format, data.post_id);
            return true;
          },
          self.callbackFMLPostSuccess, //Return looks exactly like a if exists query on a match
          function(XHR, status, errorThrown) {
            self.guessRenderAddButton(0);
            return true; //do default action
          }
        );
      }
      // don't go to href
      event.preventDefault();
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
      return self._show_error( sprintf(constants.msgs_error.ajax, status, errorThrown ) );
    };

    /**
     * Flickr API error thrown
     * @param  {string} code flickr error code
     * @param  {string} msg  flickr error message
     */
    this.handle_flickr_error = function(code, msg) {
      if ( msg === '') {
        return self._show_error( constants.msgs_error.flickr_unk );
      }
      return self._show_error( sprintf(constants.msgs_error.flickr, code, msg ) );
    };

    /**
     * WordPress FML API error thrown
     * @param {object} data the ajax return
     */
    this.handle_fml_error = function(data) {
      return self._show_error( sprintf(constants.msgs_error.fml, data.code, data.reason) );
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

      this.picturefills = [];

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

        this.picturefills.push($img);

        $li.append($img);
        self.$photo_list.append($li);
      }

      // if picturefill available, make sure we process the newly created images (firefox fix)
      if (window.picturefill) {
        window.picturefill(this.picturefills);
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
        value:    constants.msgs_pagination.load//,
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
     * @param {string} id flickr id of photo (data-id of li element clicked on)
     * @param {bool} showAltCaption whether to show the alt and caption form elements
     * @return null     
     */
    this.renderPhotoInfo = function(id) {
      var photo_data = self.photo_data[id],
          msgs = constants.msgs_attachment;

      // if ( !photo_data ) { ???; } TODO
      //console.log(photo_data);
      
      // ATTACHMENT DETAILS
      var $attach_details = $('.attachment-details',self.$media_sidebar);
      // image:
      //   declare image so we can run picturefill on it after rendering
      //   TODO: remove dependency on core _flickrData
      var $img = $('<img>').attr({
        src: self.imgUrl(photo_data._flickrData,'m'),
        srcset: self.imgUrl(photo_data._flickrData,'m')+' 1x, '+self.imgUrl(photo_data._flickrData,'z')+' 2x, '+self.imgUrl(photo_data._flickrData,'c')+' 3x',
        draggable: 'false',
        title: photo_data.title,
        alt: photo_data.alt,
      })//.css({'max-width':'240px','max-height':'240px'})
      $('.thumbnail-image',$attach_details).empty().append($img);
      // filename
      $('.filename',$attach_details).text(photo_data.title).removeClass('hidden');
      // uploaded: if not uploaded, use flickr data
      if ( photo_data.dateFormatted ) {
        $('.uploaded',$attach_details).text(photo_data.dateFormatted).removeClass('hidden');
      } else if ( photo_data.date ) {
        var date = new Date(parseInt(photo_data.date));
        $('.uploaded',$attach_details).text(date.toLocaleString()).removeClass('hidden');
      } else {
        $('.uploaded',$attach_details).addClass('hidden');
      }
      // dimensions
      if ( photo_data.width && photo_data.height ) {
        $('.dimensions',$attach_details).html(photo_data.width+' &times; '+photo_data.height).removeClass('hidden');
      } else {
        $('.dimensions',$attach_details).addClass('hidden');
      }
      // TODO: compat-meta
      // FORM
      // url
      $('label[data-setting="url"] input',$attach_details).attr({
        readonly: 'readonly',
        value: self.webUrl(photo_data._flickrData)
      });
      // title
      $('label[data-setting="title"] input',$attach_details).attr({
        readonly: 'readonly',
        value: photo_data.title
      });
      var disable_form = false;
      // if in admin menu and already added to flickr, deactivate form
      // if it's a post thumbnail and already added, deactivate form as we can only extract ID
      if ( constants.page_type == 'admin_menu' || constants.page_type == 'post_thumbnail') {
        if ( photo_data.id ) {
          disable_form = true;
        }
      }
      // caption
      if ( disable_form ) {
        $('label[data-setting="caption"] textarea',$attach_details).attr({ readonly: 'readonly' }).html(photo_data.caption);
      } else {
        $('label[data-setting="caption"] textarea',$attach_details).removeAttr('readonly').html(photo_data.caption);
      }
      // alt text
      if ( disable_form ) {
        $('label[data-setting="alt"] input',$attach_details).attr({
          readonly: 'readonly',
          value: photo_data.alt
        });
      } else {
        $('label[data-setting="alt"] input',$attach_details).attr({
          value: photo_data.alt
        }).removeAttr('readonly');
      }
      // description
      //   instead of photo_data.description use photo_data.id because 
      //   description can be empty
      if ( photo_data.id ) {
        $('label[data-setting="description"] textarea',$attach_details).attr({ readonly: 'readonly' }).html(photo_data.caption);
      } else {
        $('label[data-setting="description"] textarea',$attach_details).removeAttr('readonly').html('');
      }
      $attach_details.attr({'data-id': id}).removeClass('hidden');

      // TODO: compat-item?
      
      // ATTACHMENT DISPLAY SETTINGS
      
      var $attach_display = $('.attachment-display-settings',self.$media_sidebar);
      // Only allow injection if it's in the media library and we are injecting html
      if ( photo_data.id && ( constants.page_type == 'media_button' ) ) {
        // Alignment
        $('select[data-setting="align"]',$attach_display).val(constants.default_props.align);
        // Link To
        $('select[data-setting="link"]',$attach_display).val(constants.default_props.link);
        // TODO: add url setting
        //$('select[data-setting="linkUrl"]').attr().val(constants.default_props.link);
        // Size
        var sizes = self._generateSizesArray(photo_data.sizes);
        var $sizes_select = $('select[data-setting="size"]',$attach_display).empty();
        for ( var size in sizes ) {
          $sizes_select.append($('<option>').attr({value:size}).text(sizes[size]));
        }
        $sizes_select.val(constants.default_props.size)

        $attach_display.removeClass('hidden');
      } else {
        $attach_display.addClass('hidden');
      }

      // call picturefill
      if (window.picturefill) { window.picturefill($img); }
    };
    this._generateSizesArray = function(sizes) {
      var return_obj = {};
      for (var size in sizes) {
        var size_pruned = size.replace(/\d+$/, ''); // remove numbers from rendering as it is confusing
        return_obj[size] = size_pruned+ ' ' +sizes[size].width + ' × ' + sizes[size].height;
      }
      return return_obj;
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
    /**
     * Shortcut to renderAddButton to handle most common cases
     * 
     * @param  {Number} flickr_id The flickr id to add to the data field
     * @return {null}
     */
    this.guessRenderAddButton = function(flickr_id) {
      if (flickr_id === 0) {
        self.renderAddButton(constants.msgs_add_btn.add_to, true, 0);
        return;
      }
      var photo_data = self.photo_data[flickr_id];
      var msg;
      if ( photo_data.id ) {
        switch ( constants.page_type ) {
          case 'admin_menu': // add to media library (from media library)
            msg = constants.msgs_add_btn.already;
            break;
          default: //insert into post
            msg = constants.msgs_add_btn.insert;
        }
      } else {
        msg = constants.msgs_add_btn.add_to;
      }
      self.renderAddButton(msg, false, flickr_id);
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
      self.$select_main.on(  'change', self.changeSearchType);
      self.$select_filter.on('change', self.changeFilterType);
      self.$search_query.on(   'blur', self.blurSearchField);
      switch ( constants.page_type ) {
        case 'admin_menu': //add flickr (to media library) "overlay"
          self.$add_button.on('click', self.clickAddButtonMediaLibrary);
          break;
        case 'post_thumbnail': // set as featured image
          self.$add_button.on('click', self.clickAddButtonSetFeaturedImage);
          break;
        default: // insert media iframe
          self.$add_button.on('click', self.clickAddButtonInsertPost);
      }

      if ( constants.page_type == 'post_thumbnail' ) {
        $('a.media-modal-close').click( tb_close );
      }
    });
  } // of FMLSearchDisplay class
  fmlSearchDisplay = new FMLSearchDisplay();
	// on ready function

	$( function() {
    fmlSearchDisplay.searchPhoto(0);
	});

  function tb_close(event) {
    var win=window.dialogArguments||opener||parent||top;
    win.tb_remove();
    event.preventDefault(); 
  }

})(jQuery, window, FMLConst);
