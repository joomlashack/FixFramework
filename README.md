[![Joomlashack](https://www.joomlashack.com/images/logo_circle.png)](https://www.joomlashack.com)

Fix Framework
=============

## About
In some environments, when a library is updated Joomla will decide that certain files can't be
deleted and abruptly stop in the middle of installation. This is often due to non-standard or
unexpected file/folder permissions that confuses the Joomla installer code.

It has been a subject of some debate with no conclusive fix. It may have been resolved as you
are reading this. But as this is being written, we continue to find customers experiencing
the problem when extensions relying on our Joomlashack Framework are installed or updated.

If you experience the `AllediaFramework Not Found` or `Joomlashack Framework Not Found` problem
on your site, and you are still able to access your site admin, this extension may solve the
problem and reinstall the framework.

All you need to do is [download the zip file](https://github.com/joomlashack/FixFramework/archive/main.zip)
for this repository and install it using the Joomla Extension Manager. It will provide messages
on what it attempted to do. In particular, if all went well, you should see the message
`Reinstalling the library was successful.` 

Important Note: This plugin does not install itself. You will see ominous messages about the install
failing. This is expected and nothing to worry about.
