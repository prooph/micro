# FAQ

What follows is a discussion between @max-voloshin and @codeliner in the prooph/improoph gitter chat.
The dialog covers some detailed answers to questions that will arise when looking at prooph/micro for the first time.

Max Voloshin @max-voloshin 10:14

@codeliner do you advise to use prooph/micro instead of prooph/event-sourcing?

Alexander Miertsch @codeliner 11:50

@max-voloshin prooph/micro is in an early state. It aims to simplify event sourcing a lot due to the functional approach but it introduces a set of new problems:

- not really tested in any production system
- you need small services (ideally one php service for each aggregate)
- Docker and Nginx become your best friends

If you can live with that and want to be a pioneer then prooph/micro is an interesting choice.
We want to build an eCommerce system based on prooph/micro. Everything will be dockerized and managed by swarm cluster in the cloud. We want to see how far we can get with that approach. But until we have working code (ideally in production), I cannot recommend anything. It is an experiment. To quote @Ocramius "When you use Microservices you introduce a new kind of failures. Where you have true or false before, you now have true/false or http error" 
While I get the point of his statement, I also think that smaller, dockerized services solve a lot of other problems. What I really really like so far is, that you can reduce the boilerplate php code to a bare minimum AND with Nginx and PHP-FPM you get non blocking I/O like you would have with Node.js but inside a single PHP process you can still work with the good old synchronous style and don't need things like async/await.
tl;dr: prooph/micro is not for everybody. You need to be a) a PHP lover, who probably should be a Scala, Erlang or even Node.js developer and b) don't fear the complexity of Docker and Nginx management

Max Voloshin @max-voloshin 13:39

@codeliner thanks for the detailed answer!
Small services, Docker, Nginx, Scala –

I looked prooph/micro some time ago.

I didn't get an idea of usage of dedicated php-fpm service for each aggregate type. Increasing number of php-fpm services doesn't effect on blocking I/O, does it? BTW usually services stick to bounded contexts, not aggregate type.
Declaration of aggregate in that way https://github.com/prooph/micro-do/blob/master/service/user-write/src/Model/User.php looks more procedural than functional as for me. Which advantages do you get by that approach?

Anyway I really appreciate prooph team's effort for current prod ready solutions and discovering a more efficient way to develop applications for the future 

Alexander Miertsch @codeliner 14:53

@max-voloshin
yes, BC <-> Service 1:1 

but PHP-FPM "Service" != business service, that is really a technical service

my definition at the moment (as I said experimental) BC = Service = Nginx API Gateway + n PHP-FPM container (1 container per aggregate type)

the Nginx API Gateway is the wall in front of your BC, like an application layer would be in a monolith
regarding blocking/non blocking, the thing is that for example Node.js is single threaded but is non blocking I/O whereby PHP is normally blocking. If you handle http request routing in PHP your process is blocking longer, because it needs to route the request first, then select some data, then process the business logic (command) and if you're not using a queue also send out emails, update the read model .... All this in a single blocking process
When you move http request handling to Nginx and just start with handling the command with PHP you should be able to get a faster system, that can handle more requests in parallel without additional hardware. Also this architecture is scalable by design and emphasis async read model projections and the usage of async process managers

Can I ask back, why the user aggregate looks more procedual than functional? I'd anwser your question after you answered mine 

Max Voloshin @max-voloshin 20:43

@codeliner

> BC = Service = Nginx API Gateway + n PHP-FPM container (1 container per aggregate type)

Thanks, now it's clear :grinning:

Regarding blocking/non blocking. I agree with all things you wrote, but I don't understand how it's related to multiple php-fpm service. So why we can't work with requests in described way with one php-fpm service?
Can I ask back, why the user aggregate looks more procedural than functional? I'd answer your question after you answered mine :wink:

I think so because I see standalone functions which work with a standalone untyped data structure (array) and mixed public/private API. Yes, I also see nice pure functions, but previous observations are more important as for me. I admit I could be wrong because of incomplete visions, so I will be glad to know details from you :grinning:

Alexander Miertsch @codeliner 21:55

@max-voloshin

> but I don't understand how it's related to multiple php-fpm service

ah, now I understand your concern and btw. thank you for the discussion. It is a good way to think about the concept before coding too much. Back to the question: if you would handle more than one aggregate type within the same PHP-FPM service, you would need to do some more routing again. That alone is not a problem. Routing could be done by a command bus BUT dependency management becomes pain again. If you know that your PHP-FPM service always only handle commands of a specific aggregate type then the list of dependencies is kept very small. And that is basically the idea of Microservices: small services, focused on doing only one thing, ideally with very few dependencies so that each service of its own is very very simple to build and to maintain.

If you have those units you compose them to "higher order" services aka. bounded contexts and then again those BCs can be composed to an application/system.

So if you have very few dependencies, you don't need a DI container, which means a lot of reduced complexity. You also don't need heavy configuration and such. Just set up command dispatching in a programmatic way, invoke aggregate functions with commands, and push the outcome of command handling aka. the events to the next service that will maybe trigger more commands, send out emails and do other async stuff.

My feeling is that the overall complexity of an enterprise system can be reduced with such an approach and Microservice fans observe exactly this. Complexity is moved out of the application into infrastructure. You need to set up Docker, swarm, AWS whatever ... but tooling around such an infrastructure is really good: you get auto-scaling, self-healing systems, monitoring and so on. Also it is so much easier to throw away an entire service behind an API and replace it with something totally different that may does the same thing 10x faster... or in a more secure way or both.
I worked in a migration project where we tried to migrate a 30 years old system. It took us two years and was really anything but funny. If prooph/micro works as expected, it will be the complete opposite of such a monolith and I think this will be a perfect match for event sourcing and CQRS. I mean prooph/micro is not a new approach. See the Serverless framework for example BUT prooph/micro will be tailored to prooph. I'd also use Serverless but then I'd need to rewrite prooph in Node.js and I'm not sure if @prolic and the rest of the team would hate me for that :smiley: Anyway, Nginx + PHP-FPM + Docker + prooph are a great combination so no Serverless needed or at least no Node.js version needed for the things we want to achieve :wink:

regarding untyped data structure (array): yes, that is not 100% functional. But we said: Hey, it is still PHP, so we pick some functional ideas but mix them with the things we have in PHP and arrays for example are semi immutable because they are not passed by reference by default :wink:

Max Voloshin @max-voloshin 22:43

More granular dependency management is right argument for multiple php-fpm services, now it's clear. But maybe prooph/nano is a better name for this solution? :laughing:
arrays for example are semi immutable because they are not passed by reference by default :wink:
Hm, I didn't think about it from that POV. Immutable objects are rare in PHP: https://twitter.com/maxvoloshindev/status/558616020534173696
 Follow
 Max Voloshin @maxvoloshindev
php -r '$e=new Exception('0');$e->__construct('1');echo $e->getMessage();' works without errors and returns 1 ... #php #wat
2:23 PM - 23 Jan 2015
  Retweets   likes
:grinning:
@codeliner thank you for answers again! Frankly, I can't say I am ready to use such approach in production right now, but at least it is much more understandable for me now.

Alexander Miertsch @codeliner 23:51

you're welcome.

> I can't say I am ready to use such approach in production right now

Understandable :smiley: But look at Lambda and Serverless ... Some crazy guys do exactly that and they receive a lot of money to improve this way of designing distributed systems. Also PHP was originally designed as a scripting language. See how a request/response cycle works in PHP. The global state is bootstrapped on every request and then thrown away once the response is sent back to the client. That is still a "script" which is invoked by a process manager (PHP-FPM) and killed when it is done.

If you see it this way prooph/micro or nano :wink: makes use of the one feature in PHP that makes it easy to use on the one hand but hard to use at the same time, namely reset everything after each run.
We just remove everything from the "script" that is better placed in a component that does not die:

- http handling to a non blocking web server
- parallel processes into PHP-FPM
- event stream projections into simple long-running php scripts
- ....

If you remove all the bits where PHP sucks by design you are left with a super simple but very powerful runtime environment that does one thing and does it well:

> command handler = f(fold history match -> state, command) -> events

https://github.com/prooph/micro/issues/34#issuecomment-276124831 (by @gregoryyoung)
_
 
