<?php

namespace EasySwoole\HttpAnnotation\Swagger\EasySwooleParser;

use EasySwoole\HttpAnnotation\Swagger\Annotation\ApiSuccessTemplate;
use EasySwoole\HttpAnnotation\Annotation\Cache;
use EasySwoole\HttpAnnotation\Annotation\ObjectAnnotation;
use EasySwoole\Annotation\Annotation;
use EasySwoole\HttpAnnotation\Annotation\ParserInterface;
use EasySwoole\HttpAnnotation\Annotation\PropertyAnnotation;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiAuth;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiFail;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiFailParam;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroup;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroupAuth;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroupDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiRequestExample;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccessParam;
use EasySwoole\HttpAnnotation\AnnotationTag\CircuitBreaker;
use EasySwoole\HttpAnnotation\AnnotationTag\Context;
use EasySwoole\HttpAnnotation\AnnotationTag\Di;
use EasySwoole\HttpAnnotation\AnnotationTag\Inject;
use EasySwoole\HttpAnnotation\AnnotationTag\InjectParamsContext;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;

class Parser implements ParserInterface
{
    private $parser;

    function __construct()
    {
        $annotation = new Annotation();
        static::preDefines([
            "POST" => "POST",
            "GET" => "GET",
            "PUT" => "PUT",
            "DELETE" => "DELETE",
            "PATCH" => "PATCH",
            "HEAD" => "HEAD",
            "OPTIONS" => "OPTIONS",
            'COOKIE' => 'COOKIE',
            'HEADER' => 'HEADER',
            'FILE' => 'FILE',
            'DI' => 'DI',
            'CONTEXT' => 'CONTEXT',
            'RAW' => 'RAW',
            'JSON'=>'JSON',
            'SESSION'=>'SESSION'
        ]);
        $annotation->addParserTag(new Api());
        $annotation->addParserTag(new ApiAuth());
        $annotation->addParserTag(new ApiDescription());
        $annotation->addParserTag(new ApiFail());
        $annotation->addParserTag(new ApiFailParam());
        $annotation->addParserTag(new ApiGroup());
        $annotation->addParserTag(new ApiGroupAuth());
        $annotation->addParserTag(new ApiGroupDescription());
        $annotation->addParserTag(new ApiRequestExample());
        $annotation->addParserTag(new ApiSuccessParam());
        $annotation->addParserTag(new ApiSuccess());
        $annotation->addParserTag(new CircuitBreaker());
        $annotation->addParserTag(new Context());
        $annotation->addParserTag(new Di());
        $annotation->addParserTag(new Inject());
        $annotation->addParserTag(new InjectParamsContext());
        $annotation->addParserTag(new Method());
        $annotation->addParserTag(new Param());
        $annotation->addParserTag(new ApiSuccessTemplate());
        $this->parser = $annotation;
    }

    function parseObject(\ReflectionClass $reflectionClass): ObjectAnnotation
    {
        $parent = $reflectionClass->getParentClass();
        if($parent && $parent->getName() !== AnnotationController::class){
            if(Cache::getInstance()->get($parent->getName())){
                $parent = Cache::getInstance()->get($parent->getName());
            }else{
                $parent = $this->parseObject($parent);
            }
        }
        $cache  = Cache::getInstance()->get($reflectionClass->getName());
        if($cache){
            return $cache;
        }
        $objectAnnotation = new ObjectAnnotation();

        if($parent instanceof ObjectAnnotation){
            if($parent->getApiGroupTag()){
                $objectAnnotation->addAnnotationTag($parent->getApiGroupTag());
            }
            if($parent->getApiGroupDescriptionTag()){
                $objectAnnotation->addAnnotationTag($parent->getApiGroupDescriptionTag());
            }
            foreach ($parent->getGroupAuthTag() as $tag){
                $objectAnnotation->addAnnotationTag($tag);
            }
            foreach ($parent->getParamTag() as $tag){
                $objectAnnotation->addAnnotationTag($tag);
            }
            foreach ($parent->getOtherTags() as $otherTag){
                $objectAnnotation->addAnnotationTag($otherTag);
            }
        }
        //获取class的标签注解
        $tagList = $this->parser->getAnnotation($reflectionClass);
        array_walk_recursive($tagList,function ($item)use($objectAnnotation){
            $objectAnnotation->addAnnotationTag($item);
        });

        //获取class的方法注解
        if($parent instanceof ObjectAnnotation){
            foreach ($parent->getMethod() as $method){
                $objectAnnotation->addMethod($method);
            }
        }
        foreach ($reflectionClass->getMethods()  as $method){
            $tagList = $this->parser->getAnnotation($method);
            $method = new MethodAnnotation($method->getName());
            array_walk_recursive($tagList,function ($item)use($method){
                $method->addAnnotationTag($item);
            });
            $objectAnnotation->addMethod($method);
        }

        //获取class的成员属性注解
        if($parent instanceof ObjectAnnotation){
            foreach ($parent->getProperty() as $property){
                $objectAnnotation->addProperty($property);
            }
        }
        foreach ($reflectionClass->getProperties() as $property)
        {
            $tagList = $this->parser->getAnnotation($property);
            $property = new PropertyAnnotation($property->getName());
            array_walk_recursive($tagList,function ($item)use($property){
                $property->addAnnotationTag($item);
            });
            $objectAnnotation->addProperty($property);
        }
        Cache::getInstance()->set($reflectionClass->getName(),$objectAnnotation);
        return $objectAnnotation;
    }


    public static function preDefines($defines = [])
    {
        foreach ($defines as $key => $val) {
            if (!defined($key)) {
                define($key, $val);
            }
        }
    }
}