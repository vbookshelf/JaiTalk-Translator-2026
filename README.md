# JaiTalk Translator 2026
A minimalist browser-based English to Thai voice translator to use on-the-go. 

Initially, I thought there was no reason to build a translation app like this because the free Google Translate app is already available. My guess was that it would be superior to anything I could build. But, when I actually used it I found that it was sometimes slow and the translation response was cluttered with too much info. This meant that the app was not a pleasure to use on-the-go. 

I built JaiTalk with a minimalist UI and a fast STT interface. It may not be as accurate as Google Translate, but it should be helpful for on-the-street use cases e.g. talking to a vendor at a street food stall.

You can speak in English. The app will translate your speech into Thai. Then you can switch the direction to Thai-English and hold the phone for the Thai person to talk into. That person's speech will be translated into English. By using this method it's possible to have a basic back-and-forth conversation.

<br>

## Quick Info
- Mobile first web app
- Translates Thai-to-English and English-to-Thai
- Supports voice and text input and ouput
- Click any response to play the audio
- Minimalist UI design
- Frontend: Html, CSS, Javascript
- Backend: PHP
- Uses the OpenRouter API (qwen3.5-flash-02-23 by Alibaba)
- Uses Javascript SpeechRecognition to convert the user's speech into text
- Uses Javascript SpeechSynthesis to convert text to speech
- Can be rebranded and self-hosted on any shared hosting platform

<br>

## Deployment Notes

- Add your OpenRouter API key to the ebot_config.ini.txt file before uploading to your web host server.
- Change the name of the file to ebot_config.ini
- For added security it's best to locate the ebot_config.ini file outside the web host root folder.

<br>

## Notes
- Translation quality needs to be tested in real-life scenarios.
- The STT system on mobile seems to be unexpectedly robust to background noise. This could be because modern phones are designed to work well when the user has the speaker enabled. Tested on Android - Samsung A07.
- The voice type and gender will vary across devices and across operating systems. This will be an issue with Thai because it has gender specific ways of speaking e.g. a female voice speaking Thai like a male might sound strange.

<br>

## Revision History

Version 2.0<br>
31-July-2026<br>
Released for beta testing.

<br>

