(function ($, window, Query) {
	Query = Query.extend({
	//Query.sync = function( method, model, options ) {
	sync: function (method, model, options) {
		var args, fallback;

		// Overload the read method so Attachment.fetch() functions correctly.
		if ( 'read' === method ) {
			options = options || {};
			options.context = this;
			options.data = _.extend( options.data || {}, {
				action:  'fml-query-attachments',
				post_id: wp.media.model.settings.post.id
			});

			// Clone the args so manipulation is non-destructive.
			args = _.clone( this.args );

			// Determine which page to query.
			if ( -1 !== args.posts_per_page ) {
				args.paged = Math.round( this.length / args.posts_per_page ) + 1;
			}

			options.data.query = args;
			return wp.media.ajax( options );

		// Otherwise, fall back to Backbone.sync()
		} else {
			/**
			 * Call wp.media.model.Attachments.sync or Backbone.sync
			 */
			fallback = Attachments.prototype.sync ? Attachments.prototype : Backbone;
			return fallback.sync.apply( this, arguments );
		}
	}});
	//};
})(jQuery, window, wp.media.model.Query);
console.log(wp.media.model);
