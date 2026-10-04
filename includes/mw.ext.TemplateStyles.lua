local TemplateStyles = {}
local php

function TemplateStyles.link( title )
	-- TODO: just return <templatestyles src={title}/> when legacy parser is phased out
	return php.makeTag( title )
end

function TemplateStyles.setupInterface( options )
	-- Boilerplate
	TemplateStyles.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register this library in the "mw" global
	mw = mw or {}
	mw.ext = mw.ext or {}
	mw.ext.TemplateStyles = TemplateStyles

	package.loaded['mw.ext.TemplateStyles'] = TemplateStyles
end

return TemplateStyles
