# Change Log

## [v0.2.0](https://github.com/prooph/micro/tree/v0.2.0)

[Full Changelog](https://github.com/prooph/micro/compare/v0.1.0...v0.2.0)

**Implemented enhancements:**

- Get aggregate state version by counting recorded events [\#46](https://github.com/prooph/micro/issues/46)
- test php 7.2 on travis [\#49](https://github.com/prooph/micro/pull/49) ([prolic](https://github.com/prolic))
- userland code does not require to handle carrying the aggregate version [\#47](https://github.com/prooph/micro/pull/47) ([prolic](https://github.com/prolic))
- allow aggregate state to be an object [\#45](https://github.com/prooph/micro/pull/45) ([prolic](https://github.com/prolic))
- Refactoring [\#43](https://github.com/prooph/micro/pull/43) ([prolic](https://github.com/prolic))

## [v0.1.0](https://github.com/prooph/micro/tree/v0.1.0) (2017-04-19)
**Implemented enhancements:**

- Change command handlers [\#35](https://github.com/prooph/micro/issues/35)
- Add pdo env to php images if they are depended [\#22](https://github.com/prooph/micro/issues/22)
- add micro command for composer [\#21](https://github.com/prooph/micro/issues/21)
- Optimize for one stream per aggregate [\#16](https://github.com/prooph/micro/issues/16)
- Version vs. event no problem with snapshots [\#13](https://github.com/prooph/micro/issues/13)
- TypeError on example [\#12](https://github.com/prooph/micro/issues/12)
- add amqp publisher tests [\#8](https://github.com/prooph/micro/issues/8)
- Use new stable releases [\#41](https://github.com/prooph/micro/pull/41) ([codeliner](https://github.com/codeliner))
- kernel works with transactional event store [\#38](https://github.com/prooph/micro/pull/38) ([prolic](https://github.com/prolic))
- implement one stream per aggregate feature [\#37](https://github.com/prooph/micro/pull/37) ([prolic](https://github.com/prolic))
- Command handlers [\#36](https://github.com/prooph/micro/pull/36) ([prolic](https://github.com/prolic))
- add micro command for composer [\#31](https://github.com/prooph/micro/pull/31) ([oqq](https://github.com/oqq))
- remove AmqpProducer [\#30](https://github.com/prooph/micro/pull/30) ([prolic](https://github.com/prolic))
- change dispatch pipeline [\#23](https://github.com/prooph/micro/pull/23) ([prolic](https://github.com/prolic))
- add new commands [\#17](https://github.com/prooph/micro/pull/17) ([prolic](https://github.com/prolic))
- add setup command [\#15](https://github.com/prooph/micro/pull/15) ([prolic](https://github.com/prolic))
- Improvements [\#14](https://github.com/prooph/micro/pull/14) ([prolic](https://github.com/prolic))
- track version in example model [\#10](https://github.com/prooph/micro/pull/10) ([prolic](https://github.com/prolic))
- Snapshotter [\#9](https://github.com/prooph/micro/pull/9) ([prolic](https://github.com/prolic))
- Amqp publisher [\#7](https://github.com/prooph/micro/pull/7) ([prolic](https://github.com/prolic))
- replace Pipe with pipeline function [\#6](https://github.com/prooph/micro/pull/6) ([prolic](https://github.com/prolic))
- Functional [\#1](https://github.com/prooph/micro/pull/1) ([prolic](https://github.com/prolic))

**Fixed bugs:**

- micro:setup:php-service cli command -\> start command not working [\#20](https://github.com/prooph/micro/issues/20)
- Version vs. event no problem with snapshots [\#13](https://github.com/prooph/micro/issues/13)
- Improvements [\#14](https://github.com/prooph/micro/pull/14) ([prolic](https://github.com/prolic))
- track version in example model [\#10](https://github.com/prooph/micro/pull/10) ([prolic](https://github.com/prolic))

**Closed issues:**

- \[Question\] Should we remove state and snapshots? [\#34](https://github.com/prooph/micro/issues/34)
- Remove upstream definition from nginx site config [\#29](https://github.com/prooph/micro/issues/29)
- Remove AmqpReducer [\#26](https://github.com/prooph/micro/issues/26)
- Move cli + folder structure to a skeleton repo [\#25](https://github.com/prooph/micro/issues/25)
- Input and output streams can differ [\#5](https://github.com/prooph/micro/issues/5)
- Add message validator [\#4](https://github.com/prooph/micro/issues/4)

**Merged pull requests:**

- Handle new EventStore::load return type [\#33](https://github.com/prooph/micro/pull/33) ([codeliner](https://github.com/codeliner))
- Fix pipeline name [\#32](https://github.com/prooph/micro/pull/32) ([codeliner](https://github.com/codeliner))
- micro:setup:php-service cli command -\> start command not working [\#27](https://github.com/prooph/micro/pull/27) ([oqq](https://github.com/oqq))
- updates test case to match new implementation [\#24](https://github.com/prooph/micro/pull/24) ([oqq](https://github.com/oqq))
- SetupCommand improvements [\#19](https://github.com/prooph/micro/pull/19) ([oqq](https://github.com/oqq))
- improves SetupCommand [\#18](https://github.com/prooph/micro/pull/18) ([oqq](https://github.com/oqq))
- import FQCNs [\#11](https://github.com/prooph/micro/pull/11) ([basz](https://github.com/basz))



\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*
